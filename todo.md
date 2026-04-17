# TODO

- [ ] Convert most or all custom CSS to Bootstrap while keeping the current design.
- [ ] Improve the logic for the Standards and Policies page.
- [ ] For every new user prompt, log the requested task in both TODO and CHANGELOG.
- [x] Match Standards and Policies table layout to KPI Indicators (spacing and row numbering).
- [x] Match Internal Audits table layout to KPI Indicators (spacing and row numbering).
- [x] Fix Responses detail modal formatting and broken characters in response details.
- [x] Fix QA Records table status information and status badges.
- [x] Add Policies view modal support for reading document links and using additional qa_policies columns.
- [x] Add sample policy document files in docs folder matching seeded `document_url` values.
- [x] Improve sample policy PDF formatting to a more professional layout and structure.
- [x] Make policy files readable from View button using inline preview and fallback open/download actions.
- [x] Sync qa_policies document_url values in live database and database.sql with generated policy files.
- [x] Change policy document field to file upload input and implement backend PDF upload handling.
- [x] Fix Internal Audits empty findings display and replace garbled fallback characters.
- [x] Move policy document storage to Cloudinary and remove local docs folder/files.
- [x] Fix Cloudinary untrusted PDF access by using signed download URLs and increasing `document_url` length.
- [x] Hide policy document links and show compact PDF preview in modal.
- [x] Ensure View button shows compact PDF preview for signed Cloudinary URLs.
