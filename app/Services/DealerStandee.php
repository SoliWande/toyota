<?php

namespace App\Services;

use App\Models\Dealer;
use GdImage;

/** Single-page A4 print artwork. Lossless RGB keeps QR modules sharp at 300 dpi. */
final class DealerStandee
{
    private const WIDTH = 2480;

    private const HEIGHT = 3508;

    public function pdf(Dealer $dealer, DealerProfile $profile): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $white = imagecolorallocate($image, 255, 255, 255);
        $red = imagecolorallocate($image, 235, 10, 30);
        $black = imagecolorallocate($image, 25, 25, 28);
        imagefill($image, 0, 0, $white);
        imagefilledrectangle($image, 0, 0, self::WIDTH - 1, 35, $red);
        $logo = imagecreatefrompng(resource_path('images/standee-toyota.png'));
        $logoWidth = 320;
        $logoHeight = (int) round(imagesy($logo) * $logoWidth / imagesx($logo));
        imagecopyresampled($image, $logo, (int) ((self::WIDTH - $logoWidth) / 2), 230, 0, 0,
            $logoWidth, $logoHeight, imagesx($logo), imagesy($logo));
        $this->text($image, 'VELOZ & HILUX', 58, 650, $black);
        $this->text($image, 'KẾT NỐI CỘNG ĐỒNG', 50, 765, $black);
        $this->text($image, $dealer->name, 62, 970, $red);
        $this->text($image, 'QUÉT MÃ ĐỂ ĐĂNG KÝ', 54, 1310, $black);
        // Exactly the same bytes and canonical URL as the existing Dealer QR endpoint.
        $qr = imagecreatefromstring($profile->qr($dealer));
        imagecopyresized($image, $qr, 440, 1430, 0, 0, 1600, 1600, imagesx($qr), imagesy($qr));
        $url = $profile->url($dealer);
        $this->text($image, $url, 28, 3210, $black);
        $this->text($image, 'Vui lòng điền thông tin để đại lý hỗ trợ xác minh.', 28, 3330, $black);

        // A minimal one-page PDF image container; no external executable or network renderer.
        $deflater = deflate_init(ZLIB_ENCODING_DEFLATE);
        $compressed = '';
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $row = '';
            for ($x = 0; $x < self::WIDTH; $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $row .= chr(($pixel >> 16) & 255).chr(($pixel >> 8) & 255).chr($pixel & 255);
            }
            $compressed .= deflate_add($deflater, $row, ZLIB_NO_FLUSH);
        }
        $compressed .= deflate_add($deflater, '', ZLIB_FINISH);
        $content = "q 595.276 0 0 841.890 0 0 cm /Artwork Do Q\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.890] /Resources << /XObject << /Artwork 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>',
            '<< /Type /XObject /Subtype /Image /Width '.self::WIDTH.' /Height '.self::HEIGHT.' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length '.strlen($compressed).">>\nstream\n".$compressed."\nendstream",
            '<< /Length '.strlen($content).">>\nstream\n".$content.'endstream',
            '<< /Type /Annot /Subtype /Link /Rect [60 55 535 90] /Border [0 0 0] /A << /S /URI /URI <'.bin2hex($url).'> >> >>',
        ];
        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 7\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function text(GdImage $image, string $text, int $size, int $baseline, int $color): void
    {
        $font = resource_path('fonts/standee/Roboto-Light.ttf');
        $text = preg_replace('/[\x00-\x1f\x7f]/u', ' ', $text);
        // Preserve full Vietnamese names, wrapping by Unicode character when necessary.
        $lines = [];
        $line = '';
        foreach (mb_str_split($text) as $character) {
            $box = imagettfbbox($size, 0, $font, $line.$character);
            if ($box[2] - $box[0] > 2100 && $line !== '') {
                $lines[] = trim($line);
                $line = '';
            }
            $line .= $character;
        }
        $lines[] = trim($line);
        // Long Dealer names have a reserved 300px area; fit every line without truncation.
        if (count($lines) * $size * 1.6 > 300) {
            $this->text($image, $text, max(8, (int) ($size * .8)), $baseline, $color);

            return;
        }
        foreach ($lines as $index => $line) {
            $box = imagettfbbox($size, 0, $font, $line);
            imagettftext($image, $size, 0, (int) ((self::WIDTH - ($box[2] - $box[0])) / 2),
                (int) ($baseline + $index * $size * 1.6), $color, $font, $line);
        }
    }
}
