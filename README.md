# Kurir Malam 🌙

**Full-Stack Delivery Management System**

Kurir Malam is a full-stack delivery management platform designed to streamline delivery operations, from customer order creation and dispatcher coordination to courier assignments, delivery monitoring, and proof of delivery.

Developed independently as a portfolio project, Kurir Malam demonstrates practical full-stack engineering skills, including RESTful API development, role-based access control, database architecture, frontend development, delivery pricing, ETA estimation, and operational workflow management.

The platform provides dedicated portals for four user roles: Customer, Dispatcher, Courier, and Admin. Each portal offers role-specific functionality and access permissions.

## Tech Stack

### Backend

- PHP 8.5
- Laravel 13
- MariaDB / MySQL
- Laravel Sanctum
- RESTful API
- Role-Based Access Control (RBAC)
- Eloquent ORM
- Database Migrations
- Server-side Delivery Pricing Calculation
- File Storage for Proof of Delivery

### Frontend

- Nuxt 4
- Vue 3
- TypeScript
- CSS3
- Leaflet
- OpenStreetMap
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

The application implements four user roles with dedicated dashboards and role-based access permissions.

### 1. Admin Portal

- View total orders and user statistics
- Monitor order statuses
- View operational performance statistics
- Monitor delivery success and cancellation rates
- View active orders and average delivery duration
- Visualize daily order activity
- Browse orders with status filters
- Browse user accounts by role
- View order details and customer information
- Monitor recent delivery activities
- View delivery pricing breakdowns
- Access proof of delivery information

### 2. Customer Portal

- Customer registration and authentication
- Create instant delivery orders
- Create scheduled delivery orders
- Select pickup and delivery locations using an interactive map
- Use device GPS to determine pickup location
- View delivery price estimates before placing an order
- View detailed delivery pricing breakdowns
- View order history with pagination
- View order details
- Cancel eligible orders
- Monitor delivery status and history
- View estimated delivery information when available
- View actual delivery completion time
- View proof of delivery information

### 3. Dispatcher Portal

- View delivery orders
- Filter orders by delivery status
- View complete order details
- Retrieve available courier accounts
- Assign couriers to pending orders
- Prevent assignment to offline or busy couriers
- View courier assignment information
- Monitor delivery progress
- View order status history
- Identify who assigned a courier and when the assignment occurred
- Monitor courier availability statistics
- View Available, Busy, and Offline courier counts
- View individual courier work statuses
- View delivery pricing breakdowns
- View actual delivery completion time
- Access proof of delivery information

### 4. Courier Portal

- View assigned delivery orders
- View delivery details
- Update delivery status
- Follow the delivery workflow
- View shipment status history
- Access assignment information
- View estimated delivery information when available
- Manage work availability
- Switch between Available and Offline status
- Automatically reflect Busy status when handling an active order
- Prevent going Offline while an active order exists
- Upload proof of delivery photos
- Provide recipient name and delivery notes
- View actual delivery completion time
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
- Server-side authorization for protected operations

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
Check Courier Availability
   |
   v
Assign Available Courier
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
Upload Proof of Delivery
   |
   v
Delivered
```

## Order Status Flow

| Status | Description |
|---|---|
| `pending` | Awaiting courier assignment |
| `assigned` | Courier assigned |
| `picked_up` | Package picked up |
| `in_transit` | Package is on the way |
| `delivered` | Successfully delivered |
| `cancelled` | Order cancelled |

## Delivery Location & ETA

### Interactive Location Picker

The customer order form uses Leaflet and OpenStreetMap to provide interactive location selection.

Features include:

- Interactive map for pickup and delivery locations
- Map-based coordinate selection
- Browser GPS location support
- Latitude and longitude integration with order data

### Estimated Time of Arrival

The application includes a basic ETA calculation service implemented without a paid mapping API.

The current calculation uses:

- Haversine formula for straight-line distance
- Estimated road distance multiplier of 1.3
- Assumed average delivery speed of 25 km/h
- Additional 5-minute time buffer
- Delivery status history to determine the ETA starting point

ETA information is available for eligible orders with the required location coordinates and delivery status.

**Note:** This is a preliminary estimation model. It does not represent actual GPS tracking, live traffic conditions, or real-time courier movement.

### Actual Delivery Time

The application records delivery status history and uses the timestamp when an order reaches the `delivered` status to display its actual delivery completion time.

Actual delivery time is available in the Customer, Dispatcher, and Courier order detail views.

## Delivery Pricing System

Kurir Malam implements a server-side delivery pricing service to calculate delivery costs.

### Pricing Components

- Base delivery fee
- Distance-based fee
- Additional delivery fee
- Minimum delivery price
- Delivery type (Instant or Scheduled)

### Pricing Calculation

The backend calculates delivery distance using the Haversine formula and applies a configurable road distance multiplier.

The pricing service returns a detailed breakdown containing:

- Delivery type
- Estimated distance
- Base price
- Price per kilometer
- Distance fee
- Additional fee
- Minimum price
- Total delivery price

### Pricing Integrity

- Customers can request a delivery price estimate before creating an order.
- The backend recalculates the final price when an order is submitted.
- The final price is calculated server-side rather than trusted from frontend input.
- Historical pricing details are stored as a snapshot with the order.
- Order detail pages display the saved pricing breakdown.

This ensures that historical order prices remain consistent even if pricing configuration changes in the future.

## Courier Availability Management

The courier availability system manages whether couriers can receive new delivery assignments.

### Work Status

| Status | Description |
|---|---|
| `available` | Ready to receive a new delivery |
| `busy` | Handling an active delivery |
| `offline` | Not accepting new deliveries |

### Availability Rules

- Couriers can switch between Available and Offline.
- Busy status is derived from active delivery orders.
- Active orders include `assigned`, `picked_up`, and `in_transit`.
- Couriers cannot switch to Offline while handling an active order.
- Dispatchers can only assign orders to Available couriers.
- Busy and Offline couriers are excluded from courier assignment selection.

The Dispatcher Dashboard also provides an availability overview with courier counts and individual work statuses.

## Proof of Delivery (POD)

The application includes a Proof of Delivery feature to record delivery completion.

Implemented capabilities:

- Courier photo upload
- Image compression before upload
- Recipient name submission
- Optional delivery notes
- Stored delivery proof records
- Public storage URL for proof images
- Automatic order status transition to Delivered
- Delivery completion recorded in status history
- Proof of delivery visibility in relevant order detail views

The delivery workflow requires proof submission to complete an order.

## Operational Statistics

The Admin Portal provides an operational overview to help monitor delivery performance.

Available metrics include:

- Total orders
- Delivery success rate
- Cancellation rate
- Active orders
- Average delivery duration
- Completed deliveries
- Daily order activity over the last seven days

These statistics are calculated from the application's order records and delivery status history.

## API Endpoints

All API endpoints are prefixed with `/api`.

### Authentication

| Method | Endpoint | Description |
|---|---|---|
| POST | `/register` | Register customer |
| POST | `/login` | User login |
| POST | `/logout` | User logout |
| GET | `/me` | Get authenticated user |

### Customer

| Method | Endpoint | Description |
|---|---|---|
| POST | `/orders` | Create delivery order |
| GET | `/orders` | Get customer order history |
| GET | `/orders/{id}` | Get order details |
| POST | `/orders/{id}/cancel` | Cancel eligible order |
| POST | `/pricing/estimate` | Estimate delivery price |
| GET | `/customer/dashboard` | Customer dashboard data |

### Dispatcher

| Method | Endpoint | Description |
|---|---|---|
| GET | `/dispatcher/dashboard` | Dispatcher dashboard data |
| GET | `/dispatcher/orders` | Get delivery orders |
| GET | `/dispatcher/orders/{id}` | Get order details |
| GET | `/dispatcher/couriers` | Get couriers eligible for assignment |
| GET | `/dispatcher/courier-availability` | Get courier availability overview and summary |
| POST | `/dispatcher/orders/{id}/assign` | Assign courier |

### Courier

| Method | Endpoint | Description |
|---|---|---|
| GET | `/courier/dashboard` | Courier dashboard data |
| GET | `/courier/orders` | Get assigned orders |
| GET | `/courier/orders/{id}` | Get delivery details |
| GET | `/courier/availability` | Get courier availability |
| PATCH | `/courier/availability` | Update courier availability |
| PATCH | `/courier/orders/{id}/status` | Update delivery status |
| POST | `/courier/orders/{id}/proof` | Submit proof of delivery |

### Admin

| Method | Endpoint | Description |
|---|---|---|
| GET | `/admin/dashboard` | Get administrative and operational statistics |
| GET | `/admin/orders` | Get all delivery orders |
| GET | `/admin/users` | Get user accounts |

All protected endpoints require authentication and enforce role-based authorization.

## UI & Design

- Dark navy and gold visual identity
- Responsive mobile-first layouts
- Dedicated dashboards for all four user roles
- Shared role-based bottom navigation
- Consistent interface components
- Delivery status indicators
- Order management and detail views
- Interactive map-based location selection
- Operational statistics and data visualization
- Courier availability overview
- Delivery pricing breakdowns
- Proof of delivery information
- Responsive forms and interactive elements
- Loading, empty, and error states

## Development Status

**Currently under active development.**

### Completed

- [x] Laravel backend initialization
- [x] Database schema and relationships
- [x] Authentication with Laravel Sanctum
- [x] Role-based access control
- [x] Customer registration and login
- [x] Customer order management API
- [x] Customer dashboard and order management
- [x] Customer order history pagination
- [x] Dispatcher order management API
- [x] Dispatcher dashboard and order details
- [x] Courier assignment API and frontend
- [x] Courier assignment validation
- [x] Courier availability backend API
- [x] Courier availability management interface
- [x] Dispatcher courier availability overview
- [x] Available, Busy, and Offline work status
- [x] Prevention of assignment to unavailable couriers
- [x] Courier order management API
- [x] Courier delivery status workflow
- [x] Courier dashboard and order details
- [x] Delivery status history
- [x] Admin dashboard API
- [x] Admin operational statistics
- [x] Admin order and user listing APIs
- [x] Admin dashboard interface
- [x] Role-based navigation
- [x] Responsive mobile-first interface
- [x] Basic delivery ETA calculation
- [x] ETA integration across relevant portals
- [x] Actual delivery completion time display
- [x] Interactive customer location picker
- [x] GPS-based pickup location selection
- [x] Leaflet and OpenStreetMap integration
- [x] Delivery pricing calculation service
- [x] Customer delivery price estimation
- [x] Server-side order price validation and calculation
- [x] Historical pricing snapshots
- [x] Pricing breakdown across relevant portals
- [x] Proof of Delivery backend implementation
- [x] Proof of Delivery photo upload
- [x] Image compression for delivery proof
- [x] Recipient name and delivery notes
- [x] Proof of Delivery display in order details

### Planned Improvements

- [ ] Real-time GPS-based courier tracking
- [ ] WebSocket-based location updates
- [ ] Real-time order status notifications
- [ ] Admin user management and account creation
- [ ] Automated backend and frontend testing
- [ ] Expanded API and integration test coverage
- [ ] Production deployment
- [ ] Environment and deployment documentation
- [ ] Project architecture diagrams
- [ ] Performance and security improvements

## Author

**Independent Full-Stack Developer**

Designed and developed independently as a full-stack portfolio project, covering backend architecture, REST API development, database design, frontend implementation, authentication, delivery operations, and application integration.

---

© 2026 Kurir Malam. All rights reserved.
