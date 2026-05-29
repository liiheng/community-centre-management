# Community Activities Centre Management System

A web-based management system developed to manage community activities, participant registration, organiser approvals, feedback, and activity scheduling within a Community Activities Centre.

This project was developed as a Final Year Project (FYP) for the Bachelor of Computer Science (Honours) programme at Universiti Tunku Abdul Rahman (UTAR).

---

## Project Overview

The Community Activities Centre Management System is designed to digitalise and simplify the management of activities and events organised in a community centre.

The system supports three main user roles:

* **Admin**
* **Organiser**
* **Member**

The system allows organisers to create and manage activities, members to register for activities, and administrators to approve activities and manage users through a centralized platform.

The project follows a layered architecture design consisting of:

* Presentation Layer
* Business Logic Layer
* Database Layer

---

# Features

## Admin Features

* Approve or reject organiser registrations
* Approve or reject activities
* Delete inappropriate activities
* Manage user accounts
* View and manage feedback
* Monitor all activities

## Organiser Features

* Create activities
* Upload activity posters
* Edit or delete created activities
* View activity approval status
* Download participant list in CSV format
* View participant feedback

## Member Features

* Register and login
* View approved activities in calendar view
* Register or unregister for activities
* Add registered activities to Google Calendar
* Submit activity feedback
* Manage personal profile

---

# System Architecture

The system follows a three-layered architecture:

## 1. Presentation Layer

Provides interfaces for Admin, Organiser, and Member users through web browsers.

## 2. Business Logic Layer

Handles:

* Authentication
* Role-based access control
* Activity management
* Registration handling
* Feedback management

## 3. Database Layer

Stores:

* Users
* Activities
* Participants
* Feedback
* Rooms

---

# Technologies Used

| Technology         | Purpose                 |
| ------------------ | ----------------------- |
| PHP                | Backend Development     |
| MySQL              | Database                |
| HTML5              | Frontend Structure      |
| CSS3               | Styling                 |
| JavaScript         | Interactive Features    |
| XAMPP              | Local Server            |
| phpMyAdmin         | Database Management     |
| Visual Studio Code | Development Environment |

---

# Database Design

The system contains the following main tables:

* Users
* Activities
* Participants
* Feedback
* Rooms

### Main Relationships

* One organiser can create multiple activities
* Members can register for multiple activities
* Members can submit feedback for attended activities
* Activities are assigned to rooms

---

# System Modules

## Authentication Module

* Login
* Registration
* Role validation
* Session management

## Activity Management Module

* Activity creation
* Activity approval
* Activity registration
* Activity deletion

## Feedback Module

* Submit feedback
* View feedback
* Manage feedback

## Profile Management Module

* Update personal information
* Upload profile picture
* Change password

---

# Screenshots

## Login Page

<img width="1405" height="791" alt="Screenshot 2025-09-21 195129" src="https://github.com/user-attachments/assets/9468cf68-6019-492a-b17c-6914cbc1c37a" />

## Registration Page

<img width="1373" height="868" alt="Screenshot 2025-09-21 204233" src="https://github.com/user-attachments/assets/16b27304-847d-409c-ab27-b7abc358ac77" />

## Dashboard

<img width="1910" height="874" alt="Screenshot 2025-09-21 205310" src="https://github.com/user-attachments/assets/0e5425ae-dfa9-4826-84e5-bdc9246cc39c" />

## Activity Calendar

<img width="1898" height="875" alt="Screenshot 2025-09-21 212219" src="https://github.com/user-attachments/assets/32be618a-2a55-47f4-911a-b09d8cd6e86f" />

## Create Activity Page

<img width="1918" height="910" alt="Screenshot 2025-09-21 213659" src="https://github.com/user-attachments/assets/b68996e6-2243-4560-bb28-ad4c087f9824" />

## Activity Modal View

<img width="1919" height="909" alt="Screenshot 2025-09-21 205839" src="https://github.com/user-attachments/assets/b093399e-8c9f-4346-b63b-a4ed6684c49e" />
<img width="1165" height="780" alt="Screenshot 2025-09-21 210616" src="https://github.com/user-attachments/assets/7d6bf0a6-a586-4f85-aff8-3a9c7c15fd7a" />

## Manage Account Page

<img width="1919" height="711" alt="Screenshot 2025-09-21 211259" src="https://github.com/user-attachments/assets/e4acf287-85da-4771-8845-e3289d8df907" />

## Register Activity Page

<img width="1251" height="825" alt="Screenshot 2025-09-21 212239" src="https://github.com/user-attachments/assets/bd672951-bc3f-46b4-9b57-6554c966db3e" />
<img width="1915" height="504" alt="Screenshot 2025-09-21 212628" src="https://github.com/user-attachments/assets/22d5e987-930e-4e26-86c5-52074bcf7e87" />

## Feedback Page

<img width="1919" height="913" alt="Screenshot 2025-09-21 214501" src="https://github.com/user-attachments/assets/4ae0508b-fbc1-49ed-92f3-0873ca24c7c6" />
<img width="1919" height="775" alt="Screenshot 2025-09-21 211521" src="https://github.com/user-attachments/assets/ab90ff64-6479-41fe-87e9-b620bd998c87" />

---

# Installation Guide

## Prerequisites

Make sure the following software is installed:

* XAMPP
* Web Browser
* Visual Studio Code (optional)

---

## Setup Instructions

### 1. Clone the Repository

```bash
git clone [https://github.com/yourusername/community-centre-management.git](https://github.com/liiheng/community-centre-management.git)
```

---

### 2. Move Project Folder

Move the project folder into the XAMPP `htdocs` directory.

Example:

```text
C:\xampp\htdocs\community-centre-management
```

---

### 3. Start XAMPP

Start:

* Apache
* MySQL

---

### 4. Create Database

Open phpMyAdmin and create a database named:

```text
community_centre
```

---

### 5. Import Database

Import the SQL file located inside:

```text
/includes/community_centre.sql
```

---

### 6. Run the System

Open browser and access:

```text
http://localhost/community_management
```

---

# Demo Accounts

## Admin

Username: admin

Email: admin@gmail.com

Password: asd

## Organiser

Username: zxc

Email: zxc@gmail.com

Password: zxc

## Member

Username: qwe

Email: qwe@gmail.com

Password: qwe

---

# Project Highlights

* Role-based access control
* Dynamic calendar view
* CSV participant export
* Google Calendar integration
* Feedback management system
* Activity approval workflow
* Responsive web interface

---

# Future Improvements

* Email notification system
* Real-time chat feature
* Mobile application version
* QR attendance tracking
* Analytics dashboard
* Online payment integration
* AI-based activity recommendation

---

# Development Methodology

The project follows:

* Layered Architecture Pattern
* Modular Design Approach
* Relational Database Design

---

# Author

**Loo Li Heng**
Bachelor of Computer Science (Honours)
Universiti Tunku Abdul Rahman (UTAR)

---

# License

This project is developed for educational and academic purposes.
