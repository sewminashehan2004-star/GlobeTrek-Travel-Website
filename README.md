# GlobeTrek Travel Website

A PHP-based travel booking website that allows users to explore destinations, view travel packages, register and log in, make bookings, manage payments, and manage travel operations through dedicated customer, staff, and admin interfaces.

---

## 📖 Overview

**GlobeTrek** is a travel booking website developed to provide a simple and organized platform for customers to explore travel destinations and manage their bookings online.

The system includes separate interfaces for:

- Customers
- Staff
- Administrators

The application uses **PHP, MySQL, HTML, CSS, and JavaScript** to manage travel packages, user accounts, bookings, payments, and other travel-related operations.

---

## ✨ Main Features

### 🌍 Travel & Packages

- Explore available travel destinations
- View travel packages
- Display destination images
- View package details
- Browse different travel options
- View latest travel information
- View company information and services

### 👤 Customer Features

- Customer registration
- Customer login and authentication
- Customer profile management
- Browse travel packages
- Make travel bookings
- View booking details
- View previous bookings
- Manage booking information
- Make and manage payments
- Logout securely

### 👨‍💼 Staff Features

- Staff dashboard
- Manage travel packages
- View and manage bookings
- Manage payment information
- View customer-related booking information
- Manage travel operations

### 🛠️ Admin Features

- Admin dashboard
- Manage users
- Manage staff
- Manage travel packages
- Manage bookings
- Manage payments
- View and manage system information
- Monitor overall system activities

---

## 📋 Booking System

The booking module allows customers to:

1. Select a travel package
2. Enter booking information
3. Submit a booking
4. View booking details
5. Manage existing bookings
6. Complete payment-related processes

Booking information is stored in the database and can be managed through the appropriate customer, staff, and administrator interfaces.

---

## 💳 Payment Management

The system includes a payment management module for handling booking-related payment information.

Customers can access payment functionality through their booking workflow, while authorized staff and administrators can manage payment records.

---

## 🔐 Authentication & User Management

The system provides authentication functionality for different user roles.

### Customer

- Registration
- Login
- Profile management
- Customer authentication

### Staff

- Staff login
- Staff dashboard access
- Staff management functions

### Admin

- Administrative login
- User management
- Staff management
- System management

Role-based access helps restrict administrative and staff functions from regular customers.

---

## 🛠️ Technologies Used

- **PHP**
- **MySQL**
- **HTML5**
- **CSS3**
- **JavaScript**
- **XAMPP**
- **MySQL Database**
- **PHP Sessions**
- **File-based Web Assets**

---

# ▶️ How to Run the Project on Another PC

## 1. Install XAMPP

Download and install **XAMPP** on the computer.

Open the **XAMPP Control Panel** and start:

```text
Apache
MySQL
```

Make sure both services are running before continuing.

---

## 2. Download the Project

Download or clone this repository from GitHub.

The project folder should be named:

```text
globetrak
```

Copy the complete project folder into the XAMPP `htdocs` directory:

```text
C:\xampp\htdocs\globetrak
```

The final project path should be:

```text
C:\xampp\htdocs\globetrak
```

---

## 3. Open phpMyAdmin

Open your browser and go to:

```text
http://localhost/phpmyadmin
```

---

## 4. Create and Import the Database

The project contains the database file:

```text
database/globetrek_db.sql
```

In phpMyAdmin:

1. Open the **Import** option.
2. Select:

```text
database/globetrek_db.sql
```

3. Import the SQL file.
4. Make sure the database and required tables are created successfully.

---

## 5. Check the Database Connection

Open the following file:

```text
includes/db.php
```

Check the database connection settings and make sure they match the MySQL configuration on the new PC.

The connection normally contains values such as:

```text
Host
Username
Password
Database Name
```

For a default XAMPP MySQL setup, the username is commonly:

```text
root
```

The password is commonly blank unless it has been changed.

Make sure the **database name matches the database created from `globetrek_db.sql`**.

---

## 6. Run the Website

After completing the XAMPP and database setup, open a browser and visit:

```text
http://localhost/globetrak/
```

The GlobeTrek website should now load.

---

# 📌 Basic Setup Summary

For a quick setup on another PC:

```text
1. Install XAMPP
2. Start Apache and MySQL
3. Copy globetrak into C:\xampp\htdocs\
4. Open http://localhost/phpmyadmin
5. Import database/globetrek_db.sql
6. Check includes/db.php
7. Open http://localhost/globetrak/
```

---

## 🔄 Customer Workflow

```text
Home Page
    ↓
Register / Login
    ↓
Browse Travel Packages
    ↓
Select Package
    ↓
Create Booking
    ↓
View Booking Details
    ↓
Payment
    ↓
View / Manage Bookings
```

---

## 👨‍💼 Staff Workflow

```text
Staff Login
    ↓
Staff Dashboard
    ├── Manage Packages
    ├── Manage Bookings
    └── Manage Payments
```

---

## 🛠️ Admin Workflow

```text
Admin Login
    ↓
Admin Dashboard
    ├── Manage Users
    ├── Manage Staff
    ├── Manage Packages
    ├── Manage Bookings
    └── Manage Payments
```

---

## 📂 Project Structure

```text
GlobeTrek/
│
├── admin/
│   ├── adashboard.php
│   ├── manage-bookings.php
│   ├── manage-packages.php
│   ├── manage-payments.php
│   ├── manage-users.php
│   └── manage_staff.php
│
├── customer/
│   ├── booking.php
│   ├── booking-details.php
│   ├── cdashboard.php
│   ├── logout.php
│   ├── my-bookings.php
│   ├── packages.php
│   ├── payment.php
│   └── profile.php
│
├── staff/
│   ├── manage-bookings.php
│   ├── manage-packages.php
│   ├── manage-payment.php
│   └── sdashboard.php
│
├── css/
│   ├── style.css
│   ├── site.css
│   ├── auth.css
│   ├── packages.css
│   ├── booking.css
│   ├── payment.css
│   ├── profile.css
│   └── ...
│
├── database/
│   └── globetrek_db.sql
│
├── images/
│   ├── colombo.jpg
│   ├── ella.jpg
│   ├── galle.jpg
│   ├── kandy.jpg
│   └── sigiriya.jpg
│
├── includes/
│   ├── customer_auth.php
│   ├── db.php
│   ├── footer.php
│   └── navbar.php
│
├── index.php
├── about.php
├── contact.php
├── login.php
├── logout.php
├── register.php
├── register_process.php
├── why-us.php
├── README_BOOKING_FIX.txt
└── .gitignore
```

---

## 💾 Database

The project uses a MySQL database.

The SQL file is located at:

```text
database/globetrek_db.sql
```

This file contains the database structure and data required by the application.

---

## 🖼️ Images

The project contains destination images inside:

```text
images/
```

Examples include:

```text
colombo.jpg
ella.jpg
galle.jpg
kandy.jpg
sigiriya.jpg
```

These images are used by the website for travel destinations and package-related content.

---

## 🎥 Hero Video

The original hero video is not included in the GitHub repository because the file is larger than GitHub's standard 100 MB file limit.

If the original hero video is available, place it inside:

```text
C:\xampp\htdocs\globetrak\videos\hero.mp4
```

The rest of the website can still run without the hero video.

---

## 🧪 Basic Testing

After starting the website, test the main functions:

### Customer

- Register a new account
- Login
- Browse packages
- Create a booking
- View booking details
- Check payment page
- View previous bookings
- Open profile
- Logout

### Staff

- Login
- Open staff dashboard
- Manage packages
- Manage bookings
- Manage payments

### Admin

- Login
- Open admin dashboard
- Manage users
- Manage staff
- Manage packages
- Manage bookings
- Manage payments

---

## ⚠️ Troubleshooting

### Apache is not starting

Check whether another application is using the Apache ports.

Start Apache from the XAMPP Control Panel after resolving the port conflict.

---

### MySQL is not starting

Check whether another MySQL or MariaDB service is already running.

Stop the conflicting service and start MySQL from XAMPP.

---

### Database Connection Error

Check:

```text
includes/db.php
```

Make sure:

- MySQL is running
- Database exists
- Database name is correct
- Username is correct
- Password is correct

---

### Page Not Found

Make sure the project is located at:

```text
C:\xampp\htdocs\globetrak
```

Then open:

```text
http://localhost/globetrak/
```

Do not open PHP files directly using File Explorer.

---

### CSS or Images Not Loading

Make sure these folders exist:

```text
css/
images/
includes/
```

Also make sure the website is opened using:

```text
http://localhost/globetrak/
```

and not through a local `file:///` path.

---

## 🔒 Security Notes

Do not publish or share:

- Database passwords
- API keys
- Private credentials
- Secret configuration values

For a production environment, authentication and password storage should be implemented using stronger security practices.

---

## 🚀 Future Improvements

Possible future improvements include:

- Secure password hashing
- Online payment gateway integration
- Email booking confirmations
- Advanced package search and filtering
- Customer reviews and ratings
- Google Maps integration
- Improved mobile responsiveness
- Travel package recommendations
- Stronger role-based authorization
- Cloud deployment

---

## 👨‍💻 Author

**Rankothge Shehan Sewmina**

Software Engineering Student | Web Developer
