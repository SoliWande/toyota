# Toyota dealer directory snapshot

Source: https://www.toyota.com.vn/lien-he-dai-ly

Retrieved 2026-10-04. The official directory lists 87 locations, including branches; each location becomes a separate Dealer. Fields are extracted from the public directory HTML, not invented or taken from third-party listings.

- `toyota_source_id`: the website's `data-dealerid`, used for stable import identity.
- `code`: `TMV-{toyota_source_id}` is an application-generated internal code, **not an official dealer business code**. Existing dealer codes are preserved when matched by source ID or name.
- `province`: Toyota's province filter associated with its registration link. Some filter labels retain older province names even where the displayed address has been updated; both are preserved as published.
- Phone, address, name and opening hours retain the source's text, with whitespace collapsed. Website/social URLs are trimmed; source URLs missing a scheme receive `https://`. Missing website (2), Facebook (2) or Zalo (5) links stay null. No claims about actual operating status or remote website availability are inferred from the directory.
- New imported locations default to active. An existing Dealer's active/inactive setting, ID and code are preserved. Dealers absent from the snapshot are neither deleted nor deactivated.

Run `php artisan db:seed --class=ToyotaDealerSeeder` to import or refresh this checked-in snapshot. It does not make network calls, truncate tables, create users or alter Sales attribution. The default DatabaseSeeder includes this import. Repeated runs preserve identities and do not update unchanged records. Matching conflicts abort the entire transaction instead of merging unrelated dealers.

When refreshing the snapshot, verify the advertised directory count and unique source IDs against the website before committing it. This file is a dated snapshot, not automatic synchronization.
