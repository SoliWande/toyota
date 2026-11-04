<?php

namespace App\Services;

/** Presentation only: consumes the resolver's masked DTO, never infers Group evidence. */
final class RegistrationLookupProgress
{
    public static function steps(array $result): array
    {
        $kind = $result['kind'] ?? null;
        if (! in_array($kind, ['draft', 'found', 'not_requested', 'requested', 'member', 'supplement', 'blocked'], true)) {
            return [];
        }

        $groupConfirmed = in_array($kind, ['requested', 'member'], true)
            || (in_array($kind, ['supplement', 'blocked'], true) && ($result['group_confirmed'] ?? false) === true);
        $steps = [
            ['key' => 'registration', 'title' => 'Đăng ký thông tin', 'state' => 'completed',
                'description' => 'Đã nhận thông tin của bạn'],
            ['key' => 'group', 'title' => 'Vào group Facebook', 'state' => $groupConfirmed ? 'completed' : 'pending',
                'description' => $groupConfirmed ? 'Đã gửi yêu cầu vào group' : 'Chưa có xác nhận yêu cầu vào group.',
                'description_hidden' => false],
            ['key' => 'membership', 'title' => 'Thành viên chính thức', 'state' => 'pending',
                'description' => 'Chưa xác nhận tư cách thành viên.', 'description_hidden' => true],
        ];

        if ($kind === 'draft') {
            $steps[0] = array_replace($steps[0], ['state' => 'current', 'title' => 'Hoàn tất đăng ký',
                'description' => 'Bạn mới xong bước 1, còn bước 2 (xe và Facebook).']);
            $steps[1]['description'] = 'Hoàn tất đăng ký trước khi xin vào group.';
            $steps[1]['description_hidden'] = true;
        } elseif ($kind === 'not_requested') {
            $steps[1]['state'] = 'current';
            $steps[1]['description'] = 'Xin vào group và trả lời đúng số điện thoại, biển số.';
        } elseif ($kind === 'requested') {
            $steps[2] = array_replace($steps[2], ['state' => 'current',
                'description' => 'Đang chờ quản trị viên nhóm chấp nhận.', 'description_hidden' => false]);
        } elseif ($kind === 'member') {
            $steps[0]['description_hidden'] = true;
            $steps[1]['description'] = 'Đã xác nhận tư cách thành viên group.';
            $steps[1]['description_hidden'] = true;
            $steps[2] = array_replace($steps[2], ['state' => 'completed',
                'description' => 'Bạn đã là thành viên chính thức của nhóm.', 'description_hidden' => false]);
        } elseif ($kind === 'supplement') {
            $steps[2] = array_replace($steps[2], ['state' => 'warning', 'title' => 'Cần sửa lại thông tin',
                'description' => $result['reason'] ?? 'Cần bổ sung thông tin để xác minh.', 'description_hidden' => false]);
        } elseif ($kind === 'blocked') {
            $steps[0]['description_hidden'] = true;
            $steps[1]['description_hidden'] = $groupConfirmed;
            $steps[2] = array_replace($steps[2], ['state' => 'blocked', 'title' => 'Chưa thể xác nhận',
                'description' => $result['reason'] ?? 'Hiện chưa thể xác minh thông tin đăng ký.', 'description_hidden' => false]);
        }

        return array_map(function (array $step) {
            $step['status_label'] = match ($step['state']) {
                'completed' => 'Đã hoàn thành', 'current' => 'Đang thực hiện hoặc chờ xử lý',
                'warning' => 'Cần bổ sung', 'blocked' => 'Chưa thể xác nhận', default => 'Chưa hoàn thành',
            };
            $step['icon'] = match ($step['state']) {
                'completed' => 'done', 'current' => $step['key'] === 'membership' ? 'waiting' : 'current',
                'warning' => 'warning', 'blocked' => 'blocked', default => 'upcoming',
            };

            return $step;
        }, $steps);
    }
}
