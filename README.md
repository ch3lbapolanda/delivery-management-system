
# Kurir Malam 🌙

**Delivery Management System**

Kurir Malam is a full-stack delivery management platform designed to manage customers, delivery orders, courier assignments, and shipment tracking through an integrated REST API and web application.

Built as an independent full-stack development project, Kurir Malam focuses on practical delivery operations, role-based access control, and a responsive user experience.

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

## Project Structure

```text
kurir-malam/
├── delivery-api/          # Laravel Backend
├── delivery-web/          # Nuxt Frontend
├── .gitignore
└── README.md
```

## Core Features

### Authentication & Authorization
- User registration and login
- Token-based authentication with Laravel Sanctum
- Role-based access control
- Protected API endpoints
- Role-based dashboard routing

### Customer Portal
- Customer registration and authentication
- Create delivery orders
- View order history
- View order details
- Cancel eligible orders
- Track order status and history

### Dispatcher Portal
- View all delivery orders
- Filter orders by delivery status
- View complete order details
- Retrieve courier accounts
- Assign couriers to pending orders
- Track order status history

### Courier Portal
- View assigned delivery orders
- View delivery details
- Update delivery status
- Track delivery progress
- View shipment status history

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
| in_transit | Package on the way |
| delivered | Successfully delivered |
| cancelled | Order cancelled |

## API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/register` | Register customer |
| POST | `/api/login` | User login |
| POST | `/api/logout` | User logout |
| GET | `/api/me` | Authenticated user |
| POST | `/api/orders` | Create delivery order |
| GET | `/api/orders` | Customer order history |
| GET | `/api/orders/{id}` | Customer order details |
| POST | `/api/orders/{id}/cancel` | Cancel order |
| GET | `/api/dispatcher/orders` | Dispatcher order list |
| GET | `/api/dispatcher/orders/{id}` | Dispatcher order details |
| GET | `/api/dispatcher/couriers` | Get courier list |
| POST | `/api/dispatcher/orders/{id}/assign` | Assign courier |
| GET | `/api/courier/orders` | Get assigned orders |
| GET | `/api/courier/orders/{id}` | Courier order details |
| PATCH | `/api/courier/orders/{id}/status` | Update delivery status |

All protected endpoints require authentication and enforce role-based authorization.

## UI & Design

- Dark-themed interface
- Navy and gold visual identity
- Responsive mobile-first layouts
- Dedicated dashboards for different user roles
- Delivery status indicators
- Order history and tracking timeline

## Development Status

**Currently under active development.**

### Completed
- [x] Laravel backend initialization
- [x] Database schema and relationships
- [x] Authentication and authorization
- [x] Customer order management API
- [x] Customer portal integration
- [x] Dispatcher order management API
- [x] Dispatcher dashboard and order details
- [x] Courier assignment API and frontend
- [x] Courier order management API
- [x] Courier delivery status workflow
- [x] Courier dashboard and order details

### Planned Improvements
- [ ] Admin management dashboard
- [ ] Courier availability management
- [ ] Google Maps integration
- [ ] Real-time delivery tracking
- [ ] Delivery notifications
- [ ] Automated testing

## Author

**Independent Fullstack Developer**

Designed and developed independently as a full-stack portfolio project.

---

© 2026 Kurir Malam. All rights reserved.

