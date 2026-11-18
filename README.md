# Kurir Malam 🌙

### Full-Stack Delivery Management System

[![Laravel](https://img.shields.io/badge/Backend-Laravel%2013-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Nuxt](https://img.shields.io/badge/Frontend-Nuxt%204-00DC82?logo=nuxt.js&logoColor=white)](https://nuxt.com/)
[![Vue](https://img.shields.io/badge/UI-Vue%203-4FC08D?logo=vue.js&logoColor=white)](https://vuejs.org/)
[![TypeScript](https://img.shields.io/badge/Language-TypeScript-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![Status](https://img.shields.io/badge/Status-Active%20Development-yellow)]()

Kurir Malam is an independently developed delivery management platform
that models the end-to-end delivery process---from customer order
creation and dispatcher coordination to courier execution, live location
tracking, proof of delivery, and operational monitoring.

The application is organized into four role-based portals---**Customer,
Dispatcher, Courier, and Admin**---backed by a centralized Laravel REST
API. It focuses on practical business rules, secure role-based access,
delivery operations, and real-time communication between users.

  -------------------------------------------------------------------------------------------------------------------------------
  Project                             Details
  ----------------------------------- -------------------------------------------------------------------------------------------
  **Author**                          Chelba Polanda

  **Project type**                    Independent full-stack development project

  **Repository**                      [delivery-management-system](https://github.com/ch3lbapolanda/delivery-management-system)

  **Status**                          Active development

  **Development environment**         Android --- Termux and Acode
  -------------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

## Table of Contents

-   [Architecture](#architecture)
-   [Technology Stack](#technology-stack)
-   [Application Portals](#application-portals)
-   [Core Features](#core-features)
-   [Delivery Workflow](#delivery-workflow)
-   [Real-Time GPS Tracking](#real-time-gps-tracking)
-   [Security and Access Control](#security-and-access-control)
-   [Testing](#testing)
-   [Project Structure](#project-structure)
-   [Development Status](#development-status)
-   [Running the Tests](#running-the-tests)
-   [Development Environment](#development-environment)

## Architecture

Kurir Malam uses a separated frontend-and-backend structure:

-   **Frontend:** Nuxt 4 application providing the four role-specific
    portals.
-   **Backend:** Laravel 13 REST API responsible for authentication,
    authorization, business rules, data persistence, and event
    broadcasting.
-   **Database:** MariaDB / MySQL for application data and delivery
    records.
-   **Realtime layer:** Laravel Reverb and Laravel Echo for
    WebSocket-based updates.

The frontend communicates with the backend through REST endpoints.
Protected operations are authorized by the backend, so access control
does not depend solely on frontend navigation or visibility.

## Technology Stack

  Area                Technologies
  ------------------- ------------------------------------------------
  Backend             PHP 8.5, Laravel 13
  Database            MariaDB / MySQL
  Authentication      Laravel Sanctum
  API                 RESTful API
  Frontend            Nuxt 4, Vue 3, TypeScript
  Realtime            Laravel Reverb, Laravel Echo, Pusher JS client
  Maps                Leaflet, OpenStreetMap
  Testing             PHPUnit, Vitest
  Development tools   Termux, Acode, Git

## Application Portals

Each portal has its own operational responsibilities and access
permissions.

### Customer

-   Register and authenticate.
-   Create instant and scheduled delivery orders.
-   Select pickup and delivery locations using an interactive map.
-   Use browser GPS to select the pickup location.
-   Request a delivery price estimate before placing an order.
-   View order history, order details, and delivery status history.
-   Cancel eligible orders.
-   Track the courier's location while an order is in transit.
-   View ETA information when available.
-   View delivery completion time and proof of delivery.
-   Receive delivery-related notifications.

### Dispatcher

-   View and filter delivery orders.
-   Inspect order details and status history.
-   View courier accounts and courier availability.
-   Assign available couriers to pending orders.
-   Prevent assignment to busy or offline couriers.
-   Monitor courier availability counts and individual work statuses.
-   Track an individual courier or monitor multiple active couriers on a
    fleet map.
-   View delivery pricing, completion time, and proof of delivery.
-   Receive real-time location and order-related updates.

### Courier

-   View assigned deliveries and delivery details.
-   Update delivery status through the delivery workflow.
-   Manage availability using Available and Offline states.
-   Automatically appear Busy while handling an active delivery.
-   Prevent switching to Offline while an active order exists.
-   Share device location while an order is in transit, subject to
    browser and device permissions.
-   Upload proof of delivery photos.
-   Submit recipient name and optional delivery notes.
-   View delivery status history, assignment information, and completion
    time.
-   Receive delivery-related notifications.

### Admin

-   View operational and order statistics.
-   Monitor delivery success, cancellation, and active-order metrics.
-   Review daily order activity and average delivery duration.
-   Browse orders and user accounts.
-   Inspect order details, delivery pricing, and proof of delivery.
-   Monitor recent delivery activity.

> **Development note:** Admin user management is currently in progress.
> Account creation, editing, activation/deactivation, and related
> access-control workflows are not listed as completed features.

## Core Features

### Order Lifecycle Management

The system supports the main delivery lifecycle:

-   Customer order creation.
-   Dispatcher review and courier assignment.
-   Courier pickup and in-transit updates.
-   Delivery completion through proof of delivery.
-   Order status history for operational traceability.

Supported order statuses:

  Status         Meaning
  -------------- ---------------------------------------
  `pending`      Waiting for courier assignment
  `assigned`     A courier has been assigned
  `picked_up`    The courier has picked up the package
  `in_transit`   The delivery is in progress
  `delivered`    The delivery has been completed
  `cancelled`    The order has been cancelled

### Delivery Pricing

Delivery pricing is calculated by the backend rather than trusting a
total submitted by the frontend.

The pricing service supports:

-   Base delivery fee.
-   Distance-based fee.
-   Additional fee.
-   Minimum delivery price.
-   Instant and scheduled delivery types.
-   A detailed price breakdown for the customer.

The backend recalculates the price when an order is submitted and stores
a pricing snapshot with the order. This preserves the original pricing
details if the pricing configuration changes later.

### ETA Estimation

The application provides a preliminary delivery ETA using:

-   Haversine distance calculation.
-   A configurable estimated road-distance multiplier.
-   An assumed average delivery speed.
-   An additional time buffer.
-   Delivery status history to determine the estimation starting point.

**Current limitation:** This is a distance-based estimate. It does not
use a routing engine, live traffic data, or real-time road conditions.

### Proof of Delivery

The delivery completion workflow includes:

-   Courier photo upload.
-   Image compression before upload.
-   Recipient name.
-   Optional delivery notes.
-   Stored proof-of-delivery records.
-   Delivery completion recorded in order status history.
-   Proof visibility in relevant order detail views.

The delivery workflow requires proof submission before an order can be
completed.

### Notifications and Activity History

-   Database-backed notifications.
-   Realtime notification broadcasting.
-   Notification list and unread-count API.
-   Mark one notification as read.
-   Mark all notifications as read.
-   Notification bell integrated into all four role dashboards.
-   Realtime notification updates in the frontend.
-   Order activity timeline based on delivery status history.

## Real-Time GPS Tracking

Kurir Malam supports browser-based courier location sharing for orders
in the `in_transit` state.

### Location Updates

-   The Courier portal uses the browser Geolocation API.
-   Location updates are sent to the Laravel API.
-   Location records include available GPS metadata such as coordinates
    and measurement details.
-   Tracking is limited to the active in-transit delivery workflow.

### WebSocket Broadcasting

-   Laravel Reverb broadcasts courier location events.
-   Laravel Echo and the Pusher JS client receive events in the
    frontend.
-   Customer and Dispatcher tracking views subscribe to authorized
    private order channels.
-   The Dispatcher Fleet Monitoring dashboard can display multiple
    active couriers.
-   A polling fallback refreshes location data if realtime updates are
    unavailable.

### Fleet Monitoring

The Dispatcher portal provides a map for monitoring active courier
locations. It associates courier locations with their relevant orders
and updates map markers as new location data arrives.

GPS freshness is represented using the following states:

  Indicator   Meaning
  ----------- -----------------------------------------------------
  `LIVE`      Recent location data has been received
  `STALE`     The latest location update is delayed
  `OFFLINE`   No sufficiently recent location update is available

**Important limitation:** These indicators describe the freshness of
recorded GPS data, not definitive device connectivity. Browser
suspension, closed tabs, operating-system background restrictions,
denied location permission, unavailable GPS, or network interruption can
prevent updates. The current implementation is browser-based and is not
a native background location service.

## Security and Access Control

The backend uses Laravel Sanctum for token-based authentication and
role-based middleware for protected operations.

Implemented access-control capabilities include:

-   User registration and login.
-   Token generation and authenticated-user retrieval.
-   Logout and token revocation.
-   Protected API routes.
-   Role-based authorization for Admin, Dispatcher, Courier, and
    Customer.
-   Server-side validation of protected operations.
-   Private broadcasting channel authorization.

The application does not rely only on frontend route guards to protect
role-specific functionality.

## Testing

Automated tests are maintained for backend API behavior and frontend
utilities.

Existing backend test coverage includes:

-   Authentication and role-based authorization.
-   Order lifecycle and order integrity.
-   Courier availability and assignment restrictions.
-   Delivery pricing calculations and pricing API validation.
-   Courier location submission and tracking consistency.
-   Private broadcasting channel authorization.
-   Proof-of-delivery workflow.
-   Notification and activity-related functionality.

Frontend GPS utility tests are maintained with Vitest.

## Project Structure

``` text
delivery-management-system/
├── delivery-api/          # Laravel 13 REST API
└── delivery-web/          # Nuxt 4 frontend
```

## Development Status

**Active development**

### Implemented

-   [x] Laravel backend and Nuxt frontend initialization
-   [x] Database schema, migrations, and relationships
-   [x] Authentication with Laravel Sanctum
-   [x] Role-based access control for four user roles
-   [x] Customer registration, login, and order management
-   [x] Dispatcher order management and courier assignment
-   [x] Courier delivery workflow and availability management
-   [x] Order status history and lifecycle validation
-   [x] Admin operational dashboard and statistics
-   [x] Delivery pricing service and pricing snapshots
-   [x] Interactive location picker with Leaflet and OpenStreetMap
-   [x] GPS-based pickup location selection
-   [x] ETA estimation
-   [x] Proof-of-delivery upload and display
-   [x] Laravel Reverb WebSocket broadcasting
-   [x] Laravel Echo integration in tracking views
-   [x] Customer courier tracking
-   [x] Dispatcher per-order tracking and fleet monitoring
-   [x] GPS data freshness indicators
-   [x] Database notifications and realtime notification updates
-   [x] Order activity timeline
-   [x] Backend automated tests and frontend utility tests

### In Progress

-   [ ] Admin user listing and management workflow
-   [ ] Create dispatcher and courier accounts
-   [ ] Update user details and roles
-   [ ] Activate and deactivate user accounts
-   [ ] Revoke Sanctum tokens when an account is deactivated
-   [ ] Prevent deactivated accounts from logging in or accessing
    protected endpoints

### Planned

-   [ ] Expand backend and frontend test coverage
-   [ ] Security and performance review
-   [ ] Production deployment
-   [ ] Deployment and environment documentation
-   [ ] Project architecture diagrams

## Running the Tests

Run backend tests:

``` bash
cd delivery-api
php artisan test
```

Run frontend tests:

``` bash
cd delivery-web
npm test
```

Run frontend type checking:

``` bash
cd delivery-web
npx nuxi typecheck
```

## Development Environment

Kurir Malam is developed using an Android-only workflow with **Termux**
and **Acode**. The project is maintained in a single Git repository
containing both the Laravel API and Nuxt frontend.

This workflow demonstrates that the application is being built and
tested in a constrained mobile development environment without relying
on a laptop or desktop computer.

------------------------------------------------------------------------

## Author

**Chelba Polanda**\
Independent Full-Stack Developer

Built independently, with a focus on backend architecture, REST API
design, database modeling, business-rule implementation, frontend
development, realtime communication, and automated testing.

© 2026 Chelba Polanda. All rights reserved..
