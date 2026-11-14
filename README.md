# Kurir Malam 🌙

**Full-Stack Delivery Management System**

Kurir Malam is an independently developed full-stack delivery management
platform designed to streamline delivery operations, from customer order
creation and dispatcher coordination to courier assignments, delivery
monitoring, and proof of delivery.

The project demonstrates practical full-stack engineering across RESTful
API development, role-based access control, database architecture,
frontend development, delivery pricing, ETA estimation, GPS tracking,
and real-time fleet monitoring.

The platform provides dedicated portals for Customer, Dispatcher,
Courier, and Admin roles.

## Tech Stack

### Backend

-   PHP 8.5
-   Laravel 13
-   MariaDB / MySQL
-   Laravel Sanctum
-   Laravel Reverb
-   RESTful API
-   Role-Based Access Control (RBAC)
-   Eloquent ORM and Database Migrations
-   Server-side Delivery Pricing Calculation
-   File Storage for Proof of Delivery
-   Queued event broadcasting

### Frontend

-   Nuxt 4
-   Vue 3
-   TypeScript
-   CSS3
-   Laravel Echo
-   Pusher JS client
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

### 1. Admin Portal

-   View total orders and user statistics
-   Monitor order statuses and operational performance
-   Monitor delivery success and cancellation rates
-   View active orders and average delivery duration
-   Visualize daily order activity
-   Browse orders with status filters
-   Browse user accounts by role
-   View order details and customer information
-   Monitor recent delivery activities
-   View delivery pricing breakdowns
-   Access proof of delivery information

### 2. Customer Portal

-   Customer registration and authentication
-   Create instant and scheduled delivery orders
-   Select pickup and delivery locations using an interactive map
-   Use device GPS to determine pickup location
-   View delivery price estimates and detailed pricing breakdowns
-   View paginated order history and order details
-   Cancel eligible orders
-   Monitor delivery status and history
-   View estimated delivery information and actual completion time
-   View proof of delivery information
-   Track courier location on a map while an order is in transit
-   Receive real-time courier location updates through WebSocket
    broadcasting, with periodic polling as a fallback

### 3. Dispatcher Portal

-   View delivery orders and filter by delivery status
-   View complete order details and status history
-   Retrieve available courier accounts and assign couriers to pending
    orders
-   Prevent assignment to offline or busy couriers
-   View courier assignment information, including who assigned a
    courier and when
-   Monitor courier availability statistics and individual work statuses
-   View delivery pricing breakdowns and actual delivery completion time
-   Access proof of delivery information
-   Monitor multiple active couriers on a single fleet map
-   View courier names and associated order numbers on the fleet
    dashboard
-   Receive real-time courier GPS updates through Laravel Reverb and
    Laravel Echo
-   Use 30-second polling as a fallback for refreshing active courier
    locations
-   Monitor GPS data freshness using LIVE, STALE, and OFFLINE indicators

### 4. Courier Portal

-   View assigned delivery orders and delivery details
-   Update delivery status and follow the delivery workflow
-   View shipment status history and assignment information
-   View estimated delivery information when available
-   Manage work availability: Available, Busy, and Offline
-   Prevent going Offline while an active order exists
-   Upload proof of delivery photos
-   Provide recipient name and delivery notes
-   View actual delivery completion time
-   Share device location while an order is in transit, subject to
    browser location permission and browser execution availability

## Authentication & Authorization

The backend uses Laravel Sanctum for token-based authentication.

Implemented capabilities: - User registration and login - Token
generation and authentication - Authenticated user information - Logout
and token revocation - Protected API endpoints - Role-based middleware
and dashboard routing - Server-side authorization for protected
operations

Supported roles: - Admin - Dispatcher - Courier - Customer

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
Share GPS Location
   |
   v
Real-Time Customer & Dispatcher Tracking
   |
   v
Upload Proof of Delivery
   |
   v
Delivered
```

## Order Status Flow

  Status         Description
  -------------- -----------------------------
  `pending`      Awaiting courier assignment
  `assigned`     Courier assigned
  `picked_up`    Package picked up
  `in_transit`   Package is on the way
  `delivered`    Successfully delivered
  `cancelled`    Order cancelled

## Delivery Location & ETA

### Interactive Location Picker

The customer order form uses Leaflet and OpenStreetMap to provide: -
Interactive map for pickup and delivery locations - Map-based coordinate
selection - Browser GPS location support - Latitude and longitude
integration with order data

### Estimated Time of Arrival

The application includes a basic ETA calculation service implemented
without a paid mapping API.

The current calculation uses: - Haversine formula for straight-line
distance - Estimated road distance multiplier of 1.3 - Assumed average
delivery speed of 25 km/h - Additional 5-minute time buffer - Delivery
status history to determine the ETA starting point

ETA information is available for eligible orders with the required
location coordinates and delivery status.

**Note:** This is a preliminary estimation model. It does not represent
live traffic conditions or a routing-engine-based travel time
prediction.

### Actual Delivery Time

The application records delivery status history and uses the timestamp
when an order reaches the `delivered` status to display its actual
delivery completion time. This is available in Customer, Dispatcher, and
Courier order detail views.

## Delivery Pricing System

Kurir Malam implements a server-side delivery pricing service.

### Pricing Components

-   Base delivery fee
-   Distance-based fee
-   Additional delivery fee
-   Minimum delivery price
-   Delivery type (Instant or Scheduled)

### Pricing Calculation

The backend calculates delivery distance using the Haversine formula and
applies a configurable road distance multiplier. The pricing service
returns a detailed breakdown containing delivery type, estimated
distance, base price, price per kilometer, distance fee, additional fee,
minimum price, and total delivery price.

### Pricing Integrity

-   Customers can request a delivery price estimate before creating an
    order.
-   The backend recalculates the final price when an order is submitted.
-   The final price is calculated server-side rather than trusted from
    frontend input.
-   Historical pricing details are stored as a snapshot with the order.
-   Order detail pages display the saved pricing breakdown.

This ensures historical order prices remain consistent even if pricing
configuration changes in the future.

## Courier Availability Management

The courier availability system manages whether couriers can receive new
delivery assignments.

  Status        Description
  ------------- ---------------------------------
  `available`   Ready to receive a new delivery
  `busy`        Handling an active delivery
  `offline`     Not accepting new deliveries

Availability rules: - Couriers can switch between Available and
Offline. - Busy status is derived from active delivery orders. - Active
orders include `assigned`, `picked_up`, and `in_transit`. - Couriers
cannot switch to Offline while handling an active order. - Dispatchers
can only assign orders to Available couriers. - Busy and Offline
couriers are excluded from courier assignment selection.

The Dispatcher Dashboard provides an availability overview with courier
counts and individual work statuses.

## Real-Time GPS Tracking

Kurir Malam includes browser-based courier GPS tracking for orders in
the `in_transit` state.

### Courier Location Updates

-   The Courier portal uses the browser Geolocation API with
    `watchPosition()`.
-   Location updates are rate-limited to a minimum interval of
    approximately 8 seconds.
-   Updates with reported GPS accuracy above 100 meters are ignored.
-   Location updates are submitted to the Laravel API with latitude,
    longitude, accuracy, heading, speed, and recording timestamp.
-   GPS tracking starts for an in-transit order and stops when the order
    is no longer in transit.

### Real-Time Broadcasting

-   Courier location updates are broadcast using Laravel Reverb.
-   Laravel Echo and the Pusher JS client receive WebSocket events in
    the frontend.
-   Customer and Dispatcher order-detail pages subscribe to private
    order channels.
-   The Dispatcher Fleet Monitoring dashboard subscribes to private
    channels for active orders.
-   The `courier.location.updated` event updates the relevant courier
    marker without requiring a page reload.
-   A 30-second polling fallback refreshes the active courier location
    list.

### Dispatcher Fleet Monitoring

The Dispatcher dashboard includes a multi-courier monitoring map built
with Leaflet and OpenStreetMap.

Features include: - Display active courier locations on one map -
Associate each location with its courier and order - Update markers when
real-time GPS events arrive - Fit the map to available courier locations
when initial locations are loaded - Preserve the current map view during
subsequent marker updates - Display the latest location timestamp for
each courier

### GPS Health Monitoring

The Fleet Monitoring dashboard evaluates the freshness of each courier's
most recently recorded GPS location.

  -----------------------------------------------------------------------
  GPS Health                          Condition
  ----------------------------------- -----------------------------------
  `LIVE`                              Last GPS update is 30 seconds old
                                      or less

  `STALE`                             Last GPS update is more than 30
                                      seconds and up to 90 seconds old

  `OFFLINE`                           Last GPS update is more than 90
                                      seconds old
  -----------------------------------------------------------------------

The dashboard recalculates GPS health periodically, even when no new
WebSocket event arrives.

**Important limitation:** These indicators describe GPS data freshness,
not definitive device connectivity. A courier may appear stale or
offline if the browser tab is suspended, closed, background-restricted
by the operating system, location permission is denied, GPS is
unavailable, or network connectivity is interrupted. The current
implementation relies on browser execution and is not a native
background location service.

## Proof of Delivery (POD)

The application includes a Proof of Delivery feature to record delivery
completion.

-   Courier photo upload
-   Image compression before upload
-   Recipient name submission
-   Optional delivery notes
-   Stored delivery proof records
-   Public storage URL for proof images
-   Automatic order status transition to Delivered
-   Delivery completion recorded in status history
-   Proof of delivery visibility in relevant order detail views

The delivery workflow requires proof submission to complete an order.

## Operational Statistics

The Admin Portal provides an operational overview to help monitor
delivery performance.

Available metrics include: - Total orders - Delivery success rate -
Cancellation rate - Active orders - Average delivery duration -
Completed deliveries - Daily order activity over the last seven days

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

  -------------------------------------------------------------------------
  Method                  Endpoint                  Description
  ----------------------- ------------------------- -----------------------
  POST                    `/orders`                 Create delivery order

  GET                     `/orders`                 Get customer order
                                                    history

  GET                     `/orders/{id}`            Get order details

  POST                    `/orders/{id}/cancel`     Cancel eligible order

  POST                    `/pricing/estimate`       Estimate delivery price

  GET                     `/customer/dashboard`     Customer dashboard data

  GET                     `/orders/{id}/tracking`   Get the authenticated
                                                    customer's courier
                                                    location
  -------------------------------------------------------------------------

### Dispatcher

  ------------------------------------------------------------------------------------
  Method                  Endpoint                             Description
  ----------------------- ------------------------------------ -----------------------
  GET                     `/dispatcher/dashboard`              Dispatcher dashboard
                                                               data

  GET                     `/dispatcher/orders`                 Get delivery orders

  GET                     `/dispatcher/orders/{id}`            Get order details

  GET                     `/dispatcher/couriers`               Get couriers eligible
                                                               for assignment

  GET                     `/dispatcher/courier-availability`   Get courier
                                                               availability overview
                                                               and summary

  GET                     `/dispatcher/live-couriers`          Get latest GPS
                                                               locations for active
                                                               in-transit orders

  GET                     `/dispatcher/orders/{id}/tracking`   Get the latest location
                                                               for a specific order

  POST                    `/dispatcher/orders/{id}/assign`     Assign courier
  ------------------------------------------------------------------------------------

### Courier

  Method   Endpoint                          Description
  -------- --------------------------------- -----------------------------
  GET      `/courier/dashboard`              Get courier dashboard data
  GET      `/courier/orders`                 Get assigned orders
  GET      `/courier/orders/{id}`            Get delivery details
  GET      `/courier/availability`           Get courier availability
  PATCH    `/courier/availability`           Update courier availability
  PATCH    `/courier/orders/{id}/status`     Update delivery status
  POST     `/courier/orders/{id}/location`   Submit courier GPS location
  POST     `/courier/orders/{id}/proof`      Submit proof of delivery

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
authorization. Private broadcasting channels also use server-side
channel authorization.

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
-   Courier availability overview
-   Delivery pricing breakdowns
-   Proof of delivery information
-   Multi-courier fleet monitoring map
-   GPS health indicators
-   Loading, empty, and error states

## Development Status

**Currently under active development.**

### Completed

-   [x] Laravel backend initialization
-   [x] Database schema and relationships
-   [x] Authentication with Laravel Sanctum
-   [x] Role-based access control
-   [x] Customer registration and login
-   [x] Customer order management API
-   [x] Customer dashboard and order management
-   [x] Customer order history pagination
-   [x] Dispatcher order management API
-   [x] Dispatcher dashboard and order details
-   [x] Courier assignment API and frontend
-   [x] Courier assignment validation
-   [x] Courier availability backend API
-   [x] Courier availability management interface
-   [x] Dispatcher courier availability overview
-   [x] Available, Busy, and Offline work status
-   [x] Prevention of assignment to unavailable couriers
-   [x] Courier order management API
-   [x] Courier delivery status workflow
-   [x] Courier dashboard and order details
-   [x] Delivery status history
-   [x] Admin dashboard API
-   [x] Admin operational statistics
-   [x] Admin order and user listing APIs
-   [x] Admin dashboard interface
-   [x] Role-based navigation
-   [x] Responsive mobile-first interface
-   [x] Basic delivery ETA calculation
-   [x] ETA integration across relevant portals
-   [x] Actual delivery completion time display-   [x] Interactive customer location picker
-   [x] GPS-based pickup location selection
-   [x] Leaflet and OpenStreetMap integration
-   [x] Delivery pricing calculation service
-   [x] Customer delivery price estimation
-   [x] Server-side order price validation and calculation
-   [x] Historical pricing snapshots
-   [x] Pricing breakdown across relevant portals
-   [x] Proof of Delivery backend implementation
-   [x] Proof of Delivery photo upload
-   [x] Image compression for delivery proof
-   [x] Recipient name and delivery notes
-   [x] Proof of Delivery display in order details
-   [x] Courier GPS location submission
-   [x] Laravel Reverb WebSocket broadcasting
-   [x] Laravel Echo integration in Customer and Dispatcher tracking
    pages
-   [x] Real-time customer courier tracking
-   [x] Real-time dispatcher per-order courier tracking
-   [x] WebSocket connection status indicator
-   [x] Dispatcher multi-courier Fleet Monitoring dashboard
-   [x] Real-time fleet marker updates through private order channels
-   [x] 30-second fleet polling fallback
-   [x] GPS health monitoring with LIVE, STALE, and OFFLINE states
-   [x] Automatic GPS freshness recalculation
-   [x] Manual multi-browser testing of GPS health transitions

### Planned Improvements

-   [ ] Native background GPS tracking for courier devices
-   [ ] Real-time order status notifications
-   [ ] Admin user management and account creation
-   [ ] Automated frontend testing
-   [ ] Expanded backend feature and integration test coverage
-   [ ] Additional GPS tracking reliability and edge-case handling
-   [ ] Fleet trip history and route playback
-   [ ] Production deployment
-   [ ] Environment and deployment documentation
-   [ ] Project architecture diagrams
-   [ ] Performance and security improvements

## Testing

The backend includes automated tests covering core delivery services and
workflows.

The latest recorded backend test run passed **41 tests with 148
assertions**, including tests for: - Delivery ETA calculation - Delivery
pricing calculation - Courier availability rules - Courier GPS location
submission and authorization - Order integrity and pricing snapshots -
Order lifecycle and delivery proof workflow

Real-time GPS and Fleet Monitoring have also been manually tested using
separate browser sessions for Dispatcher and Courier accounts.

## Current Limitations

-   Courier GPS tracking currently depends on browser Geolocation API
    execution.
-   Browser tabs may be suspended or throttled in the background,
    interrupting location updates.
-   GPS health states measure the age of the latest received location,
    not definitive device or network connectivity.
-   ETA is a preliminary estimate based on straight-line distance and
    configured assumptions, not live traffic or route-engine data.
-   Fleet Monitoring currently focuses on active in-transit orders and
    latest courier locations; historical route playback and trip
    analytics are not yet implemented.

## Author

**Independent Full-Stack Developer**

Designed and developed independently as a full-stack portfolio project,
covering backend architecture, REST API development, database design,
frontend implementation, authentication, delivery operations, GPS
tracking, real-time communication, and application integration.

------------------------------------------------------------------------

© 2026 Kurir Malam. All rights reserved.
