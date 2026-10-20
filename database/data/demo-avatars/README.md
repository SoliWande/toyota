# Demo Sales avatars

Generated on 2026-10-19 using the built-in `image_gen` tool (not the CLI). These are fictional people, for local demo accounts only.

Saved originals: `database/data/demo-avatars/portrait-01.png`, `portrait-02.png`, `portrait-03.png`.

The three portraits are reused across the 50 reserved demo accounts. The current weekly top three receive different portraits. Runtime JPEG thumbnails (maximum 512 px) are stored per account on the private `sales_avatars` disk, not under `public/`. Existing uploaded avatars are preserved.

Local setup after migration:

```powershell
& 'D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe' artisan db:seed --class=DevelopmentSalesAvatarSeeder --no-interaction
```

This seeder is restricted to local/testing and only fills empty avatar paths for `demo.sales.01@example.test` through `demo.sales.50@example.test` with names beginning `[DEMO] `. It does not create accounts or change account roles, email, status, passwords, Dealer assignments, customer submissions, awards, or evidence.

## Final prompts

### portrait-01.png

Use case: photorealistic-natural. Asset: square avatar for a fictional demo automotive sales account in a Vietnamese community application. Single Vietnamese man aged around 30, short neat black hair, white business shirt, friendly natural expression. Head and shoulders centered with generous space for circular cropping, soft light gray studio background, realistic professional portrait, soft daylight. No text, no logo, no watermark. Entirely fictional person.

### portrait-02.png

Use case: photorealistic-natural. Asset: square avatar for a fictional demo automotive sales account in a Vietnamese community application. Single Vietnamese woman aged around 28, shoulder length straight black hair, navy business blazer over cream blouse, friendly natural expression. Head and shoulders centered with generous space for circular cropping, soft light gray studio background, realistic professional portrait, soft daylight. No text, no logo, no watermark. Entirely fictional person.

### portrait-03.png

Use case: photorealistic-natural. Asset: square avatar for a fictional demo automotive sales account in a Vietnamese community application. Single Vietnamese man aged around 38, neatly styled black hair, subtle rectangular glasses, light blue business shirt, approachable confident expression. Head and shoulders centered with generous space for circular cropping, soft light gray studio background, realistic professional portrait, soft daylight. No text, no logo, no watermark. Entirely fictional person.
