GLOBETREK BOOKING UI UPDATE
============================

Updated:
- customer/booking.php
- css/booking.css
- css/site.css (existing project theme retained)
- includes/navbar.php (shared navbar retained/fixed)
- css/about.css (compact hero override)
- css/why.css (compact hero override)
- css/contact.css (compact hero override)
- css/my-bookings.css (compact hero override)
- database/update_booking_ui.sql

Booking page now uses external travel visuals instead of the package database image.
The homepage hero video is not changed.

Run:
1. Copy/extract the globetrak folder to C:\xampp\htdocs\
2. Start Apache and MySQL in XAMPP
3. Import database/globetrek_db.sql if needed
4. Run database/update_booking_ui.sql only if your database is missing those columns
5. Open http://localhost/globetrak/
6. Hard refresh the browser with Ctrl+Shift+R

Important:
- The booking page is a demo/student booking flow.
- The database stores only booking information; no real payment gateway is included here.
