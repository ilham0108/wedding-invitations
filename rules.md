# MASTER PROMPT

# SAAS ONLINE INVITATION PLATFORM

Build a production-ready **Online Invitation SaaS Platform**.

The platform allows customers to create beautiful digital invitations, customize templates, manage guests, generate personalized invitation links, send invitations through WhatsApp, monitor RSVP responses, and manage their invitation from a client dashboard.

The system must be designed as a **multi-user SaaS application**, not as a single invitation website.

The architecture must support thousands of users, multiple invitations per user, many templates, subscription plans, payment gateways, WhatsApp providers, analytics, and optional custom domains.

# Don'ts

- Don't expose IDs directly on the front end
  Don't include any credentials or environment-specific information on the front end.

# Dos

- Use the MVC pattern
- Write clean code
  You can check out some of my projects in the same project directory and adopt my coding style

---

# 1. PRODUCT CONCEPT

The product is an online invitation builder similar conceptually to a combination of:

- Canva-style customization
- SaaS dashboard
- Digital invitation website
- Guest management system
- WhatsApp campaign system
- RSVP management

Main user journey:

Landing Page
→ Register
→ Dashboard
→ Create Invitation
→ Choose Template
→ Customize
→ Add Guests
→ Publish
→ Generate personalized links
→ Send via WhatsApp
→ Monitor invitation activity
→ Receive RSVP
→ Manage event

---

# 2. CORE PRINCIPLE

DO NOT build each invitation as a hard-coded Blade page.

The invitation must be generated dynamically from:

Template

- Template Configuration
- Invitation Data
- Invitation Sections
- Theme Configuration

Concept:

Template
↓
Template Schema
↓
Invitation
↓
Invitation Configuration
↓
Invitation Sections
↓
Public Renderer

This allows administrators to add new templates without rewriting the invitation engine.

---

# 3. TECHNOLOGY STACK

## Backend

- Laravel 12+
- PHP 8.3+
- MySQL 8+ / MariaDB
- Laravel Queue
- Laravel Scheduler
- Laravel Notifications
- Laravel Events
- Laravel Policies
- Laravel Cache
- Laravel Storage

## Dashboard

- Blade
- Livewire 3
- Alpine.js
- Tailwind CSS

## Public Invitation

- Blade
- Alpine.js
- Vanilla JavaScript where appropriate

## Optional

- Redis
- Laravel Horizon
- S3-compatible object storage

Architecture must work without Redis initially, but be ready to use Redis for production scaling.

---

# 4. DESIGN DIRECTION

The application should look like a modern commercial SaaS.

Design characteristics:

- Elegant
- Minimal
- Premium
- Spacious
- Modern
- Clean typography
- Soft borders
- Subtle shadows
- Smooth transitions
- Responsive
- Mobile-first

Avoid:

- Generic Bootstrap admin appearance
- Excessive gradients
- Excessive animations
- Overcrowded dashboards
- Large unnecessary shadows
- Old-fashioned tables

Use a consistent design system.

---

# 5. APPLICATION STRUCTURE

Create three primary areas:

## Marketing Website

/

## Client Dashboard

/dashboard

## Admin Panel

/admin

## Public Invitation

/invitation/{slug}

Optional:

/i/{slug}

/{custom-domain}

---

# 6. MULTI-TENANT MODEL

A user can own multiple invitations.

Example:

User
├── Wedding John & Jane
├── Birthday Sarah
└── Anniversary Robert & Lisa

Every invitation must belong to a user.

Authorization must ensure:

User A cannot access User B's invitation.

Use Laravel Policies.

Never rely only on hidden frontend UI for authorization.

---

# 7. USER ROLES

Roles:

### SUPER ADMIN

Permissions:

- Manage users
- Manage invitations
- Manage templates
- Manage template categories
- Manage packages
- Manage subscriptions
- Manage orders
- Manage payments
- Manage WhatsApp providers
- Manage system settings
- Manage CMS content
- View analytics
- View audit logs
- Suspend accounts

### CLIENT

Permissions:

- Manage own profile
- Create invitations
- Manage own invitations
- Choose templates
- Customize invitations
- Manage guests
- Manage RSVP
- Manage WhatsApp campaigns
- View analytics
- Manage subscription
- Manage domain

---

# 8. LANDING PAGE

Create a premium SaaS landing page.

Sections:

## Hero

Headline:

"Create Beautiful Digital Invitations in Minutes"

Supporting text:

"Create, personalize, and share elegant digital invitations with powerful guest management and WhatsApp integration."

Buttons:

- Create Invitation
- Explore Templates

Hero should show an invitation preview/mockup.

---

## Template Showcase

Display templates in attractive cards.

Categories:

- Wedding
- Engagement
- Birthday
- Anniversary
- Graduation
- Baby Shower
- Corporate
- Event
- Other

Features:

- Category filtering
- Template preview
- Use Template button

---

## How It Works

Step 1:
Choose a template

Step 2:
Customize your invitation

Step 3:
Add your guests

Step 4:
Share via WhatsApp

Step 5:
Track RSVP

---

## Features

Show:

- Beautiful Templates
- Customizable Design
- Guest Management
- Personalized Invitations
- WhatsApp Bulk Sharing
- RSVP
- Event Countdown
- Gallery
- Google Maps
- Digital Gift
- Analytics
- Custom Domain

---

## Pricing

Packages must be dynamic from database.

Example:

FREE
BASIC
PREMIUM
BUSINESS

Do not hard-code prices.

---

## FAQ

Create CMS-ready FAQ section.

---

# 9. AUTHENTICATION

Implement:

- Register
- Login
- Logout
- Forgot password
- Reset password
- Email verification
- Profile
- Change password

Optional architecture:

- Google OAuth
- Social login

---

# 10. CLIENT DASHBOARD

Dashboard layout:

Sidebar:

Dashboard
My Invitations
Templates
Guests
WhatsApp
RSVP
Analytics
Subscription
Settings

Top bar:

- Search
- Notifications
- User profile
- Help

---

# 11. DASHBOARD HOME

Show:

Total Invitations
Published Invitations
Total Guests
Total RSVP
WhatsApp Messages
Invitation Views

Cards should be clickable.

Show:

### Recent Invitations

Columns:

Invitation
Template
Status
Views
Guests
RSVP
Updated

Actions:

- Edit
- Preview
- Share
- Analytics
- More

---

# 12. INVITATION CREATION FLOW

When user clicks:

"Create Invitation"

Open wizard.

## Step 1

Choose event type:

Wedding
Birthday
Engagement
Anniversary
Graduation
Baby Shower
Corporate
Other

## Step 2

Choose template.

## Step 3

Basic event information.

## Step 4

Create invitation.

Redirect to invitation editor.

---

# 13. INVITATION MODEL

Invitation fields:

id
user_id
template_id
title
slug
status
event_type
published_at
expires_at
created_at
updated_at

Statuses:

draft
published
expired
archived

Slug must be unique.

---

# 14. TEMPLATE ENGINE

This is one of the most important components.

Templates must NOT be hard-coded.

Each template contains:

- Name
- Slug
- Category
- Thumbnail
- Preview
- Description
- Schema
- Default configuration
- Theme configuration
- Supported sections

Example:

Template:

"Elegant Wedding"

Schema:

{
"sections": [
"cover",
"couple",
"quote",
"event",
"countdown",
"story",
"gallery",
"rsvp",
"gift",
"maps",
"closing"
]
}

The template engine renders sections dynamically.

---

# 15. SECTION SYSTEM

Create reusable invitation sections.

Initial sections:

1. Cover
2. Couple
3. Quote
4. Countdown
5. Event
6. Story
7. Timeline
8. Gallery
9. Video
10. RSVP
11. Wishes
12. Gift
13. Maps
14. Dress Code
15. Contact
16. Closing

Each section should have:

- Unique ID
- Type
- Order
- Enabled/disabled
- Configuration
- Theme settings

Example:

section_type:

cover

configuration:

{
"title": "John & Jane",
"subtitle": "The Wedding",
"background": "...",
"overlay": true
}

---

# 16. SECTION BUILDER

The client must be able to:

- Enable section
- Disable section
- Reorder sections
- Edit section
- Duplicate section
- Delete section

Use drag-and-drop sorting.

Example:

☰ Cover
☰ Couple
☰ Event
☰ Gallery
☰ RSVP
☰ Gift

User can drag sections to change order.

---

# 17. INVITATION EDITOR

Create a modern editor.

Desktop layout:

LEFT:
Section/navigation panel

CENTER:
Live invitation preview

RIGHT:
Property editor

Example:

---

| Sections | Preview | Settings |
| | | |
| Cover | | Title |
| Couple | INVITATION | Font |
| Event | | Color |
| Gallery | | Image |
| RSVP | | |

---

On mobile:

Editor becomes:

- Section list
- Fullscreen preview
- Bottom sheet property editor

---

# 18. LIVE PREVIEW

Preview must update without page reload.

Modes:

- Mobile
- Tablet
- Desktop

Buttons:

Preview
Save
Publish

Show loading state while saving.

---

# 19. THEME CUSTOMIZATION

Each invitation can customize:

- Primary color
- Secondary color
- Background color
- Text color
- Font heading
- Font body
- Button style
- Border radius
- Section spacing

Provide presets:

Elegant
Classic
Modern
Minimal
Luxury
Romantic

---

# 20. FONT SYSTEM

Template can define recommended fonts.

Allow client to select from safe web fonts.

Do not allow arbitrary external font URLs.

Potential font categories:

Serif
Sans Serif
Display
Handwritten

---

# 21. COVER SECTION

Fields:

- Title
- Subtitle
- Background image
- Overlay
- Alignment
- Text color
- Animation

Optional:

- Opening animation
- "Open Invitation" button

---

# 22. COUPLE / HOST SECTION

For wedding:

- Groom name
- Groom photo
- Bride name
- Bride photo
- Parents
- Description

For birthday:

- Person name
- Photo
- Age

For corporate:

- Organization
- Speaker
- Event host

The section should be configurable depending on event type.

---

# 23. EVENT SECTION

Fields:

- Event title
- Date
- Start time
- End time
- Venue
- Address
- Description

Multiple events must be supported.

Example:

Akad Nikah
10:00

Reception
19:00

Do not assume every invitation has only one event.

---

# 24. COUNTDOWN

Display:

Days
Hours
Minutes
Seconds

Countdown must use the event timezone.

Handle expired events gracefully.

---

# 25. GALLERY

Features:

- Upload multiple images
- Drag reorder
- Delete
- Set cover
- Lazy loading
- Image optimization

Use:

- WebP
- AVIF where supported
- Responsive image sizes

Never load huge original images unnecessarily.

---

# 26. STORY / TIMELINE

Allow client to create multiple story entries.

Fields:

- Date
- Title
- Description
- Image

Example:

2019
First Meeting

2022
Engagement

2026
Wedding

---

# 27. VIDEO

Support:

- YouTube
- Vimeo

Do not require uploading large video files initially.

Use thumbnail/preview.

---

# 28. MAPS

Fields:

- Venue
- Address
- Google Maps URL
- Latitude
- Longitude

Button:

"Open Google Maps"

---

# 29. DIGITAL GIFT

Support:

Bank account
E-wallet
Gift address

Example:

Bank:
BCA

Account:
1234567890

Name:
John Doe

Provide:

Copy button

Never expose unnecessary private data.

---

# 30. MUSIC

Allow:

- Enable/disable music
- Upload MP3
- Select predefined music

Important:

Autoplay restrictions vary by browser.

Music should start after user interaction whenever required.

---

# 31. PERSONALIZED INVITATION

Every guest can have a unique URL.

Example:

/invitation/john-jane/abc123

Display:

"Dear Mr. Robert"

Guest data should be injected into the invitation safely.

Generate secure random tokens.

Do not expose sequential guest IDs.

---

# 32. GUEST MANAGEMENT

Guest fields:

- Name
- Phone
- Email
- Group
- Number of guests
- Table
- RSVP
- Notes
- Token
- Invitation status

Features:

- Create
- Edit
- Delete
- Search
- Filter
- Sort
- Bulk actions

---

# 33. GUEST GROUPS

Example:

Family
Friends
Office
VIP
Vendor

Allow filtering by group.

---

# 34. IMPORT GUESTS

Support:

CSV
Excel

Expected columns:

Name
Phone
Email
Group
Guests

Validate:

- Required name
- Phone format
- Duplicate phone
- Duplicate guest
- Invalid rows

Show import preview before committing.

Example:

Imported:
450

Valid:
438

Invalid:
12

---

# 35. EXPORT GUESTS

Export:

CSV
Excel

Allow filtering before export.

---

# 36. RSVP

RSVP fields:

- Guest name
- Attendance
- Number of guests
- Meal preference
- Message

Statuses:

pending
attending
not_attending

Allow RSVP deadline.

After deadline:

Show configurable message.

---

# 37. RSVP DASHBOARD

Cards:

Total Invited
Attending
Not Attending
Pending
Total People

Charts:

Attendance
Guest Count
RSVP over time

---

# 38. GUESTBOOK

Guest can submit:

Name
Message

Client can:

- Approve
- Hide
- Delete

Optional:

Auto-approve

---

# 39. WHATSAPP SYSTEM

WhatsApp must be designed as a provider abstraction.

Interface:

WhatsAppProviderInterface

Methods:

sendMessage()
sendBulk()
getMessageStatus()

Providers:

MetaWhatsAppProvider
FonnteProvider
WablasProvider
TwilioProvider

Provider should be configurable from admin.

---

# 40. WHATSAPP MESSAGE TEMPLATE

Allow client to create message templates.

Example:

Hello {{guest_name}},

We would like to invite you to:

{{event_name}}

Date:
{{event_date}}

Invitation:
{{invitation_url}}

Thank you.

Supported variables:

{{guest_name}}
{{event_name}}
{{event_date}}
{{event_time}}
{{venue}}
{{invitation_url}}
{{rsvp_url}}

Provide variable insertion buttons in UI.

---

# 41. WHATSAPP CAMPAIGN

Campaign fields:

- Name
- Invitation
- Message template
- Recipients
- Status
- Created at

Statuses:

draft
scheduled
processing
completed
cancelled

---

# 42. BULK WHATSAPP FLOW

User selects guests.

Example:

450 guests selected.

System:

Create campaign
↓
Create recipients
↓
Queue jobs
↓
Send messages
↓
Receive provider response
↓
Update status

Never send 450 messages synchronously in one HTTP request.

Use Laravel Queue.

---

# 43. WHATSAPP QUEUE

Each recipient becomes a queue job.

Implement:

- Retry
- Backoff
- Rate limiting
- Failure handling
- Logging

Statuses:

pending
processing
sent
delivered
failed

---

# 44. CAMPAIGN ANALYTICS

Display:

Total recipients
Sent
Delivered
Failed
Opened
Clicked

Progress:

450 total
420 sent
400 delivered
20 failed
30 pending

---

# 45. SCHEDULED WHATSAPP

Allow client to schedule campaigns.

Example:

Send tomorrow at 09:00.

Use:

Laravel Scheduler

- Queued Jobs

Timezone must be configurable.

---

# 46. WHATSAPP USAGE LIMIT

Package limits should support:

messages_per_month

or:

messages_per_invitation

Example:

FREE:
50 messages

BASIC:
500 messages

PREMIUM:
2,000 messages

Track usage.

Prevent campaign from exceeding quota.

---

# 47. SUBSCRIPTION SYSTEM

Database:

plans
subscriptions
subscription_items
orders
payments

Plans should be configurable.

Example features:

max_invitations
max_guests
max_whatsapp_messages
custom_domain
analytics
premium_templates
remove_branding

Do not hard-code these limits.

---

# 48. FEATURE LIMIT SYSTEM

Create a reusable feature/limit service.

Example:

SubscriptionLimitService

Methods:

canCreateInvitation()
canAddGuest()
canSendWhatsApp()
canUseTemplate()
canUseCustomDomain()

This allows plan restrictions to be centrally controlled.

---

# 49. PAYMENT

Architecture should support:

- Midtrans
- Xendit
- Tripay

Flow:

Create order
↓
Payment gateway
↓
Webhook
↓
Verify signature
↓
Update payment
↓
Activate subscription

Never trust frontend payment status.

---

# 50. ADMIN TEMPLATE MANAGEMENT

Admin can:

Create template
Edit template
Duplicate template
Delete template
Publish/unpublish
Assign category

Template fields:

Name
Slug
Description
Thumbnail
Preview
Category
Schema
Theme
Status

---

# 51. TEMPLATE BUILDER FOR ADMIN

Create an admin interface to configure template structure.

Example:

Template:

Elegant Wedding

Sections:

Cover
Couple
Quote
Event
Countdown
Gallery
RSVP
Gift
Maps
Closing

Admin can:

- Add section
- Remove section
- Reorder
- Set default values
- Configure theme
- Preview

---

# 52. ADMIN CMS

Admin can manage:

- Homepage hero
- Features
- FAQ
- Pricing
- Footer
- Terms
- Privacy policy
- Contact information

Avoid hard-coded marketing content where practical.

---

# 53. ANALYTICS

Track public invitation events:

invitation_view
guest_open
rsvp_open
rsvp_submit
whatsapp_click
maps_click
gift_click
gallery_view

Do not collect unnecessary personal information.

---

# 54. ANALYTICS DASHBOARD

Display:

Views
Unique visitors
RSVP
RSVP conversion
WhatsApp clicks
Maps clicks
Gift clicks

Charts:

Views by date
RSVP by date
Device distribution

---

# 55. PERFORMANCE

Public invitation pages must be fast.

Implement:

- Laravel caching
- Query optimization
- Lazy loading
- Image optimization
- Browser caching
- CDN-ready architecture
- Database indexes

Avoid N+1 queries.

Use eager loading.

---

# 56. SEO

Each invitation:

Dynamic title
Meta description
OG title
OG description
OG image
Canonical URL

Generate sitemap.

Public invitation should be indexable only if enabled.

Allow client:

"Allow search engines"

ON/OFF.

---

# 57. CUSTOM DOMAIN

Prepare architecture for:

platform.com/invitation/john-jane

subdomain:

john-jane.platform.com

custom domain:

johnandjane.com

Database:

domains

Fields:

domain
invitation_id
verification_status
ssl_status

Initial implementation may support platform URL first, but architecture must be ready for custom domains.

---

# 58. MEDIA MANAGEMENT

Create reusable media system.

Media belongs to:

User
Invitation
Section

Support:

- Upload
- Delete
- Replace
- Reorder

Validate:

- MIME
- Size
- Dimensions

Prevent malicious uploads.

---

# 59. SECURITY

Implement:

- CSRF
- XSS prevention
- SQL injection prevention
- Rate limiting
- Authorization
- Policies
- Secure tokens
- File validation
- Webhook verification
- Secure credentials

Use Laravel best practices.

---

# 60. AUDIT LOG

Track:

Login
Invitation created
Invitation updated
Template changed
Guest imported
Guest deleted
Campaign created
Campaign sent
Payment
Subscription changes

Fields:

user
action
entity
entity_id
metadata
IP
user_agent
timestamp

---

# 61. NOTIFICATIONS

Notify users about:

- Registration
- Payment success
- Payment failure
- Subscription expiry
- RSVP received
- WhatsApp campaign completed
- Invitation published

Use Laravel Notifications.

---

# 62. EMAIL

Architecture should support:

SMTP
Mailgun
Brevo
Amazon SES

Use Laravel Mail/Notifications.

---

# 63. ADMIN DASHBOARD

Metrics:

Users
Active subscriptions
Invitations
Published invitations
Guests
RSVP
WhatsApp messages
Revenue

Charts:

New users
New invitations
Revenue
Subscriptions

---

# 64. ADMIN USER MANAGEMENT

Admin can:

- Search
- Filter
- View user
- View invitations
- View subscription
- Suspend
- Activate
- Delete

---

# 65. DATABASE

At minimum create:

users
roles
permissions
invitations
invitation_templates
template_categories
invitation_sections
invitation_settings
guests
guest_groups
rsvps
guest_messages
media
whatsapp_campaigns
whatsapp_campaign_recipients
whatsapp_messages
subscription_plans
subscriptions
subscription_items
orders
payments
analytics_events
domains
notifications
audit_logs
system_settings

Use:

- ULID/UUID where appropriate
- indexes
- foreign keys
- soft deletes
- timestamps

---

# 66. DATABASE RELATIONSHIPS

Expected relationship:

User
hasMany Invitations

Invitation
belongsTo User
belongsTo Template
hasMany Sections
hasMany Guests
hasMany RSVP
hasMany Campaigns
hasMany Analytics

Template
hasMany Invitations
belongsTo Category

Guest
belongsTo Invitation
hasOne RSVP

Campaign
belongsTo Invitation
hasMany Recipients

Subscription
belongsTo User
belongsTo Plan

Order
belongsTo User
belongsTo Plan

---

# 67. ROUTE STRUCTURE

Example:

/login
/register

/dashboard

/dashboard/invitations
/dashboard/invitations/create
/dashboard/invitations/{invitation}/edit
/dashboard/invitations/{invitation}/preview

/dashboard/guests
/dashboard/whatsapp
/dashboard/rsvp
/dashboard/analytics
/dashboard/subscription
/dashboard/settings

/admin
/admin/users
/admin/templates
/admin/categories
/admin/plans
/admin/orders
/admin/payments
/admin/whatsapp
/admin/settings

/invitation/{slug}
/invitation/{slug}/{token}

---

# 68. LIVEWIRE COMPONENTS

Create reusable Livewire components such as:

InvitationList
InvitationCreator
InvitationEditor
SectionManager
SectionSettings
GuestTable
GuestImport
GuestForm
RSVPDashboard
WhatsAppCampaign
CampaignRecipients
SubscriptionCard
AnalyticsDashboard

---

# 69. UI COMPONENTS

Create reusable Blade components:

Button
Input
Select
Modal
Dropdown
Toast
Card
Badge
Table
Pagination
Tabs
EmptyState
ConfirmDialog
FileUploader
ImageUploader
ColorPicker
Toggle
DatePicker

---

# 70. ERROR HANDLING

Provide meaningful error messages.

Examples:

"Invitation could not be published."

"WhatsApp provider is not configured."

"You have reached your guest limit."

"This template is only available on Premium."

"Payment verification failed."

"Invitation not found."

Do not expose stack traces in production.

---

# 71. EMPTY STATES

Every major page should have a useful empty state.

Example:

No invitations:

"You haven't created an invitation yet."

Button:

"Create your first invitation"

No guests:

"Start building your guest list."

Button:

"Add Guests"

---

# 72. DEMO DATA

Create realistic seeders.

Admin:

admin@example.com

Demo user:

demo@example.com

Create:

5 templates
4 categories
4 plans
1 demo invitation
20 demo guests
RSVP records
WhatsApp campaign

Passwords must come from environment variables or development seed configuration.

---

# 73. TESTING

Create tests for critical functionality.

Feature tests:

- Registration
- Login
- Invitation authorization
- Create invitation
- Publish invitation
- Guest CRUD
- Guest import
- RSVP
- Subscription limit
- Payment webhook
- WhatsApp campaign

Unit tests:

InvitationService
GuestService
SubscriptionLimitService
WhatsAppService

---

# 74. API DESIGN

Even if the dashboard primarily uses Livewire, structure the business logic so an API can be introduced later.

Potential API:

/api/v1/invitations
/api/v1/guests
/api/v1/rsvp
/api/v1/templates
/api/v1/analytics

Use API Resources.

---

# 75. CACHE STRATEGY

Cache:

Templates
Template categories
Plans
System settings

Invalidate cache when admin changes configuration.

---

# 76. QUEUE JOBS

Create jobs:

SendWhatsAppMessage
ProcessWhatsAppCampaign
ProcessGuestImport
OptimizeImage
TrackAnalytics
SendNotification
ProcessPaymentWebhook

---

# 77. EVENTS

Potential events:

InvitationCreated
InvitationPublished
GuestAdded
RSVPSubmitted
WhatsAppCampaignCompleted
PaymentCompleted
SubscriptionActivated

---

# 78. LOGGING

Use structured logging.

Important logs:

WhatsApp API request/result
Payment webhook
Invitation publishing
Queue failures
Authentication failures

Never log:

Passwords
API secrets
Payment secrets
Full authentication tokens

---

# 79. RESPONSIVE DESIGN

Desktop:

Sidebar + editor + preview

Tablet:

Collapsible sidebar

Mobile:

Bottom navigation / drawer

Public invitation:

Mobile-first.

---

# 80. PWA-READY

Prepare frontend architecture so the dashboard can eventually become a PWA.

Do not implement native mobile application yet.

---

# 81. BACKUP / DATA SAFETY

Design data relationships carefully.

Important user data:

Invitation
Guest
RSVP
Media
Subscription

Use soft deletion where appropriate.

Avoid cascading deletion of important data unless explicitly intended.

---

# 82. BUSINESS MODEL

Prepare architecture for future monetization:

Free plan
Paid invitation
Monthly subscription
Annual subscription
WhatsApp credits
Premium templates
Custom domain
White-label

Do not implement all monetization features immediately, but architecture should support them.

---

# 83. FUTURE FEATURES

Design architecture to allow:

- QR check-in
- Seating arrangement
- Digital wedding gift
- Guest table assignment
- WhatsApp reminder
- Email invitation
- SMS
- Custom domain
- White label
- Affiliate/referral system
- Coupon
- Discount
- Template marketplace
- AI invitation content generation
- AI design assistance

Do not implement these unless required for MVP.

---

# 84. MVP PRIORITY

The first working version should prioritize:

1. Authentication
2. Dashboard
3. Invitation CRUD
4. Template system
5. Invitation editor
6. Public invitation
7. Guest management
8. Personalized guest links
9. RSVP
10. WhatsApp integration
11. WhatsApp queue
12. Basic subscription
13. Admin panel

Everything else can be secondary.

---

# 85. DEVELOPMENT PHASES

Do NOT attempt to generate the entire system blindly in one step.

Work sequentially.

## PHASE 1 — FOUNDATION

Implement:

- Laravel
- Authentication
- User
- Roles
- Database
- Dashboard layout
- Admin layout

Verify before continuing.

---

## PHASE 2 — TEMPLATE ENGINE

Implement:

- Template model
- Template categories
- Template schema
- Sections
- Theme configuration
- Template renderer

Create at least 3 demo templates.

Verify public rendering.

---

## PHASE 3 — INVITATION EDITOR

Implement:

- Section management
- Drag reorder
- Property editor
- Theme editor
- Live preview
- Autosave

Verify editor.

---

## PHASE 4 — GUEST SYSTEM

Implement:

- Guest CRUD
- Groups
- Import
- Export
- Personal tokens

Verify authorization.

---

## PHASE 5 — RSVP

Implement:

- Public RSVP
- RSVP dashboard
- Guestbook

---

## PHASE 6 — WHATSAPP

Implement:

- Provider interface
- Provider configuration
- Campaign
- Queue
- Recipient status
- Retry
- Usage tracking

---

## PHASE 7 — SUBSCRIPTION

Implement:

- Plans
- Limits
- Subscription
- Orders
- Payment abstraction

---

## PHASE 8 — ANALYTICS

Implement:

- Event tracking
- Invitation analytics
- Campaign analytics

---

## PHASE 9 — ADMIN

Complete:

- User management
- Templates
- Categories
- Plans
- Orders
- WhatsApp
- Settings
- Audit logs

---

# 86. IMPORTANT AI CODING INSTRUCTIONS

When implementing this project:

1. Do not overwrite existing functionality without checking dependencies.
2. Do not create duplicate models/services/components.
3. Inspect the existing project structure before adding files.
4. Follow Laravel conventions.
5. Use migrations instead of directly modifying the database.
6. Use Form Requests for validation.
7. Use Policies for authorization.
8. Use Services for complex business logic.
9. Use Jobs for long-running operations.
10. Use Events for decoupled actions.
11. Use transactions when multiple related records must be updated.
12. Avoid N+1 queries.
13. Add indexes to frequently queried columns.
14. Write tests for important functionality.
15. Keep controllers thin.
16. Keep Livewire components focused.
17. Do not place business logic inside Blade.
18. Do not hard-code package limits.
19. Do not hard-code templates.
20. Do not hard-code WhatsApp provider logic.

---

# 87. CRITICAL TEMPLATE ARCHITECTURE

The template system must support this future scenario:

Admin creates:

Template A
Template B
Template C
Template D
Template E

Without changing:

Invitation model
Invitation controller
Guest system
RSVP system
WhatsApp system

Only template configuration should change.

The public renderer should interpret template configuration.

Example:

$template
→ sections
→ section renderer
→ section configuration
→ theme
→ output HTML

---

# 88. CRITICAL INVITATION ARCHITECTURE

Invitation data should be separated from template presentation.

For example:

Invitation:

John & Jane

Event:

20 December 2026

Venue:

Jakarta Convention Center

These data must not be stored inside a template.

Template only controls:

How the data is displayed.

This makes templates reusable.

---

# 89. CRITICAL SECURITY RULE

Every request involving:

Invitation
Guest
Campaign
RSVP
Media
Subscription

must verify ownership or permission.

Example:

User A requests:

/dashboard/invitations/ULID-OF-USER-B

System must return:

403 Forbidden

even if User A knows the ID.

---

# 90. PRODUCTION READINESS

Prepare:

.env.example

README.md

database migrations

seeders

factories

tests

queue configuration

storage configuration

mail configuration

WhatsApp configuration

payment configuration

deployment documentation

---

# 91. README

README must contain:

Requirements
Installation
Environment configuration
Database setup
Storage setup
Queue setup
Scheduler setup
Mail configuration
WhatsApp configuration
Payment configuration
Testing
Production deployment

---

# 92. DEPLOYMENT

Document deployment for typical Linux server:

Nginx
PHP-FPM
MySQL/MariaDB
Supervisor
Redis optional
SSL
Queue worker
Scheduler

Commands should be included.

---

# 93. FINAL UX FLOW

The final user experience should feel like:

### Step 1

"Create your invitation"

### Step 2

"Choose a design"

### Step 3

"Customize your invitation"

### Step 4

"Add your guests"

### Step 5

"Publish"

### Step 6

"Share via WhatsApp"

### Step 7

"Track your guests"

### Step 8

"See who is coming"

---

# 94. SUCCESS CRITERIA

The project is considered successful when:

- A user can register.
- User can create an invitation.
- User can choose a template.
- User can customize sections.
- User can reorder sections.
- User can upload images.
- User can preview invitation.
- User can publish invitation.
- Public invitation works.
- User can add guests.
- User can import guests.
- Every guest can have a personalized link.
- Guest can RSVP.
- Client can see RSVP.
- Client can create WhatsApp campaign.
- Campaign uses Laravel Queue.
- Message status is tracked.
- Subscription limits are enforced.
- Admin can manage templates.
- Admin can manage packages.
- Admin can manage users.
- Unauthorized users cannot access another user's data.

---

# 95. DEVELOPMENT RULE

After completing each phase:

1. Run migrations.
2. Run tests.
3. Check authorization.
4. Check responsive UI.
5. Check database relationships.
6. Check error handling.
7. Check performance.
8. Report what was implemented.
9. Report files created/modified.
10. Report remaining work.

Then continue to the next phase.

Do not claim a feature is complete if it is only a mock UI.

For external integrations such as WhatsApp and payment gateways, clearly separate:

- UI
- service abstraction
- provider implementation
- configuration
- webhook
- queue
- production credentials

---

# FINAL OBJECTIVE

Build a polished, scalable, maintainable **Online Invitation SaaS Platform** that can eventually become a commercial product.

The architecture must make it easy to add:

new templates,
new invitation sections,
new WhatsApp providers,
new payment providers,
new subscription plans,
new event types,
new features,

without rewriting the core system.
