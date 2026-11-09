# Kurir Malam 🌙

**Full-Stack Delivery Management System**

Kurir Malam is a full-stack delivery management platform designed to
streamline delivery operations, from customer order creation and
dispatcher coordination to courier assignments and shipment monitoring.

Developed independently as a portfolio project, Kurir Malam demonstrates
practical full-stack development skills, including RESTful API design,
role-based access control, database management, frontend architecture,
and third-party service integration.

The platform provides dedicated portals for customers, dispatchers,
couriers, and administrators, each with role-specific functionality and
access permissions.

## Tech Stack

### Backend

-   PHP 8.5
-   Laravel 13
-   MariaDB / MySQL
-   Laravel Sanctum
-   RESTful API
-   Role-Based Access Control (RBAC)
-   Eloquent ORM

### Frontend

-   Nuxt 4
-   Vue 3
-   TypeScript
-   CSS3
-   Leaflet
-   OpenStreetMap
-   Responsive Mobile-First Design

## Project Structure

``` text
kurir-malam/
├── delivery-api/          # Laravel Backend
├── delivery-web/          # Nuxt Frontend
├── .gitignore
└── README.md
```

## User Roles & Portals

The application implements four user roles with dedicated dashboards and
access permissions.

### 1. Admin Portal

-   View total orders and user statistics
-   Monitor order statuses
-   View operational performance statistics
-   Monitor delivery success and cancellation rates
-   View active orders and average delivery duration
-   Visualize daily order activity
-   Browse orders with status filters
-   Browse user accounts by role
-   View order details and customer information
-   Monitor recent delivery activities

### 2. Customer Portal

-   Customer registration and authentication
-   Create instant delivery orders
-   Create scheduled delivery orders
-   Select pickup and delivery locations using an interactive map
-   Use device GPS to determine the pickup location
-   View order history
-   View order details
-   Cancel eligible orders
-   Monitor delivery status and history
-   View estimated delivery information when available

### 3. Dispatcher Portal

-   View delivery orders
-   Filter orders by delivery status
-   View complete order details
-   Retrieve courier accounts
-   Assign couriers to pending orders
-   View courier assignment information
-   Monitor delivery progress
-   View order status history
-   Identify who assigned a courier and when the assignment occurred

### 4. Courier Portal

-   View assigned delivery orders
-   View delivery details
-   Update delivery status
-   Follow the delivery workflow
-   View shipment status history
-   Access assignment information
-   View estimated delivery information when available
-   Access a dedicated courier dashboard

## Authentication & Authorization

The backend uses Laravel Sanctum for token-based authentication.

Implemented capabilities:

-   User registration and login
-   Token generation and authentication
-   Authenticated user information
-   Logout and token revocation
-   Protected API endpoints
-   Role-based middleware
-   Role-based dashboard routing

Supported roles:

-   Admin
-   Dispatcher
-   Courier
-   Customer

## Delivery Workflow

``` text
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

  Status       Description
  ------------ -----------------------------
  pending      Awaiting courier assignment
  assigned     Courier assigned
  picked_up    Package picked up
  in_transit   Package is on the way
  delivered    Successfully delivered
  cancelled    Order cancelled

## Delivery Location & ETA

### Interactive Location Picker

The customer order form uses Leaflet and OpenStreetMap to provide
interactive location selection.

Features include:

-   Interactive map for pickup and delivery locations
-   Map-based coordinate selection
-   Browser GPS location support
-   Latitude and longitude integration with order data

### Estimated Time of Arrival

The application includes a basic ETA calculation service implemented
without a paid mapping API.

The current calculation uses:

-   Haversine formula for straight-line distance
-   Estimated road distance multiplier of 1.3
-   Assumed average delivery speed of 25 km/h
-   Additional 5-minute time buffer

ETA information is available for eligible orders with the required
location coordinates and delivery status.

**Note:** This is a preliminary estimation model. It does not represent
actual GPS tracking, live traffic conditions, or real-time courier
movement.

## Operational Statistics

The Admin Portal provides an operational overview to help monitor
delivery performance.

Available metrics include:

-   Total orders
-   Delivery success rate
-   Cancellation rate
-   Active orders
-   Average delivery duration
-   Completed deliveries
-   Daily order activity over the last seven days

These statistics are calculated from the application's order records and
delivery status history.

## API Endpoints

All API endpoints are prefixed with `/api`.

### Authentication

  Method   Endpoint      Description
  -------- ------------- ------------------------
  POST     `/register`   Register customer
  POST     `/login`      User login
  POST     `/logout`     User logout
  GET      `/me`         Get authenticated user

### Customer

  Method   Endpoint                Description
  -------- ----------------------- ----------------------------
  POST     `/orders`               Create delivery order
  GET      `/orders`               Get customer order history
  GET      `/orders/{id}`          Get order details
  POST     `/orders/{id}/cancel`   Cancel eligible order
  GET      `/customer/dashboard`   Customer dashboard data

### Dispatcher

  Method   Endpoint                           Description
  -------- ---------------------------------- ---------------------------
  GET      `/dispatcher/dashboard`            Dispatcher dashboard data
  GET      `/dispatcher/orders`               Get delivery orders
  GET      `/dispatcher/orders/{id}`          Get order details
  GET      `/dispatcher/couriers`             Get courier accounts
  POST     `/dispatcher/orders/{id}/assign`   Assign courier

### Courier

  Method   Endpoint                        Description
  -------- ------------------------------- ------------------------
  GET      `/courier/dashboard`            Courier dashboard data
  GET      `/courier/orders`               Get assigned orders
  GET      `/courier/orders/{id}`          Get delivery details
  PATCH    `/courier/orders/{id}/status`   Update delivery status

### Admin

  -----------------------------------------------------------------------
  Method                  Endpoint                Description
  ----------------------- ----------------------- -----------------------
  GET                     `/admin/dashboard`      Get administrative and
                                                  operational statistics

  GET                     `/admin/orders`         Get all delivery orders

  GET                     `/admin/users`          Get user accounts
  -----------------------------------------------------------------------

All protected endpoints require authentication and enforce role-based
authorization.

## UI & Design

-   Dark navy and gold visual identity
-   Responsive mobile-first layouts
-   Dedicated dashboards for all four user roles
-   Shared role-based bottom navigation
-   Consistent interface components
-   Delivery status indicators
-   Order management and detail views
-   Interactive map-based location selection
-   Operational statistics and data visualization
-   Responsive forms and interactive elements
-   Loading, empty, and error states

## Development Status

**Currently under active development.**

### Completed

-   [x] Laravel backend initialization
-   [x] Database schema and relationships
-   [x] Authentication with Laravel Sanctum
-   [x] Role-based access control
-   [x] Customer order management API
-   [x] Customer dashboard and order management
-   [x] Dispatcher order management API
-   [x] Dispatcher dashboard and order details
-   [x] Courier assignment API and frontend
-   [x] Courier assignment information
-   [x] Courier order management API
-   [x] Courier delivery status workflow
-   [x] Courier dashboard and order details
-   [x] Delivery status history
-   [x] Admin dashboard API
-   [x] Admin operational statistics
-   [x] Admin order and user listing APIs
-   [x] Admin dashboard interface
-   [x] Login and registration
-   [x] Role-based navigation
-   [x] Responsive mobile-first interface
-   [x] Basic delivery ETA calculation
-   [x] ETA integration across relevant portals
-   [x] Interactive customer location picker
-   [x] GPS-based pickup location selection
-   [x] Leaflet and OpenStreetMap integration

### Planned Improvements

-   [ ] Proof of Delivery (photo or delivery confirmation)
-   [ ] Admin user management and account creation
-   [ ] Courier availability management
-   [ ] Improved ETA calculation starting from pickup
-   [ ] Real-time GPS-based courier tracking
-   [ ] WebSocket-based location updates
-   [ ] Delivery notifications
-   [ ] Automated backend and frontend testing
-   [ ] Production deployment
-   [ ] Project documentation and architecture diagrams

## Author

**Independent Full-Stack Developer**

Designed and developed independently as a full-stack portfolio project,
covering backend architecture, REST API development, database design,
frontend implementation, authentication, and application integration.

------------------------------------------------------------------------

© 2026 Kurir Malam. All rights reserved.


