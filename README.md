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
- Company information and services

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
