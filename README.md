
# Kurir Malam 🌙

**Full-Stack Delivery Management System**

Kurir Malam is a full-stack delivery management platform designed to streamline delivery operations, including customer order creation, dispatcher management, courier assignments, and shipment status monitoring.

Built as an independent full-stack development project, Kurir Malam demonstrates RESTful API development, role-based access control, database management, and responsive multi-role web application development.

## Tech Stack

### Backend
- PHP 8.5
- Laravel 13
- MariaDB / MySQL
- Laravel Sanctum
- RESTful API
- Role-Based Access Control (RBAC)
- Eloquent ORM

### Frontend
- Nuxt 4
- Vue 3
- TypeScript
- CSS3
- Responsive Mobile-First Design

## Project Structure

```text
kurir-malam/
├── delivery-api/          # Laravel Backend
├── delivery-web/          # Nuxt Frontend
├── .gitignore
└── README.md
```

## User Roles & Portals

The application implements four user roles with dedicated dashboards and access permissions.

### 1. Admin Portal

- View total orders and user statistics
- Monitor order statuses
- View recent delivery activities
- Browse orders with status filters
- Browse user accounts by role
- View order details
- View user profile information

### 2. Customer Portal

- Customer registration and authentication
- Create instant delivery orders
- Create scheduled delivery orders
- View order history
- View order details
- Cancel eligible orders
- Monitor delivery status and history

### 3. Dispatcher Portal

- View delivery orders
- Filter orders by delivery status
- View complete order details
- Retrieve courier accounts
- Assign couriers to pending orders
- Monitor delivery progress
- View order status history

### 4. Courier Portal

- View assigned delivery orders
- View delivery details
- Update delivery status
- Follow the delivery workflow
- View shipment status history
- Access a dedicated courier dashboard

## Authentication & Authorization

The backend uses Laravel Sanctum for token-based authentication.

Implemented capabilities:
- User registration and login
- Token generation and authentication
- Authenticated user information
- Logout and token revocation
- Protected API endpoints
- Role-based middleware
- Role-based dashboard routing

Supported roles:
- Admin
- Dispatcher
- Courier
- Customer

## Delivery Workflow

```text
Customer
   |
   v
Create Delivery Order
   |
   v
Dispatcher
   |
   v
Assign Courier
   |
   v
Courier
   |
   v
Pick Up Package
   |
   v
In Transit
   |
   v
Delivered
```

## Order Status Flow

| Status | Description |
|---|---|
| pending | Awaiting courier assignment |
| assigned | Courier assigned |
| picked_up | Package picked up |
| in_transit | Package is on the way |
| delivered | Successfully delivered |
| cancelled | Order cancelled |

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/register` | Register customer |
| POST | `/api/login` | User login |
| POST | `/api/logout` | User logout |
| GET | `/api/me` | Get authenticated user |

### Customer

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/orders` | Create delivery order |
| GET | `/api/orders` | Get customer order history |
| GET | `/api/orders/{id}` | Get order details |
| POST | `/api/orders/{id}/cancel` | Cancel eligible order |
| GET | `/api/customer/dashboard` | Customer dashboard data |

### Dispatcher

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/dispatcher/dashboard` | Dispatcher dashboard data |
| GET | `/api/dispatcher/orders` | Get delivery orders |
| GET | `/api/dispatcher/orders/{id}` | Get order details |
| GET | `/api/dispatcher/couriers` | Get courier accounts |
| POST | `/api/dispatcher/orders/{id}/assign` | Assign courier |

### Courier

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/courier/dashboard` | Courier dashboard data |
| GET | `/api/courier/orders` | Get assigned orders |
| GET | `/api/courier/orders/{id}` | Get delivery details |
| PATCH | `/api/courier/orders/{id}/status` | Update delivery status |

### Admin

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/admin/dashboard` | Get administrative statistics |
| GET | `/api/admin/orders` | Get all delivery orders |
| GET | `/api/admin/users` | Get user accounts |

All protected endpoints require authentication and enforce role-based authorization.

## UI & Design

- Dark navy and gold visual identity
- Responsive mobile-first layouts
- Dedicated dashboards for all four roles
- Shared role-based bottom navigation
- Consistent interface components
- Delivery status indicators
- Order management and detail views
- Responsive forms and interactive elements

## Development Status

**Currently under active development.**

### Completed

- [x] Laravel backend initialization
- [x] Database schema and relationships
- [x] Authentication with Laravel Sanctum
- [x] Role-based access control
- [x] Customer order management API
- [x] Customer dashboard and order management
- [x] Dispatcher order management API
- [x] Dispatcher dashboard and order details
- [x] Courier assignment API and frontend
- [x] Courier order management API
- [x] Courier delivery status workflow
- [x] Courier dashboard and order details
- [x] Admin dashboard API
- [x] Admin dashboard interface
- [x] Admin order and user listing APIs
- [x] Login page
- [x] Global role-based bottom navigation
- [x] Responsive mobile-first interface

### Planned Improvements

- [ ] Complete navigation functionality for all user roles
- [ ] Admin user management and account creation
- [ ] Courier availability management
- [ ] Google Maps integration
- [ ] GPS-based delivery tracking
- [ ] Real-time delivery location updates
- [ ] Delivery notifications
- [ ] Automated backend and frontend testing
- [ ] Production deployment

## Author

**Independent Full-Stack Developer**

Designed and developed independently as a full-stack portfolio project, covering backend architecture, REST API development, database design, frontend implementation, and application integration.

---

© 2026 Kurir Malam. All rights reserved.


