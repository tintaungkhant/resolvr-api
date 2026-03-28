# Project Report

> **[Technical README (README.md)](README.md)** — Architecture, API endpoints, SLA rules, and setup instructions.

## First Impressions

This was a well-designed case study. The requirements were realistic and clear, and I picked up valuable domain knowledge around ticketing systems and SLA management that I didn't have before.

## Step 1: Domain Knowledge Research

When I first received the case study, I understood everything except SLA — I had no prior experience with SLA concepts. I researched how SLA works in real-world ticketing systems by studying Zendesk and Jira's SLA implementations. That's where I understood how SLA deadlines are derived from priority, how the clock pauses during holds, and how "due soon" thresholds work.

## Step 2: Planning & Timeline

I broke down the 8-hour timebox as follows:

**Estimated:**


| Phase       | Time    |
| ----------- | ------- |
| Backend     | 3 hours |
| Frontend    | 3 hours |
| Fine tuning | 2 hours |
| Buffer      | 1 hour  |


**Actual:**


| Phase         | Time       |
| ------------- | ---------- |
| Backend       | 4 hours    |
| Frontend      | 2.5 hours  |
| Fine tuning   | 2 hours    |
| Documentation | 30 minutes |


It was tight. I didn't allocate a separate planning phase — I planned the architecture while researching SLA, which saved time.

## Step 3: Database Design

The database design looks straightforward at first glance, but has a few tricky parts.

### SLA & Plans

Initially, I planned to have organisation plans (free, pro, ultra, etc.) that would determine different SLA resolution times per priority. For example, an "urgent" ticket for a free-plan organisation might have an 8-hour SLA, while the same priority for an ultra-plan organisation would be 2 hours. I dropped this due to the timebox, and went with a flat priority-based SLA instead.

### Authentication Model (the hardest decision)

The most difficult design decision was how to model authentication across roles. I considered three options:

**Option 1: Single table for all roles.**
Simple to implement, but storing columns for all roles in one table creates tight coupling at the database level. Changing a column for one role risks affecting others. Different roles have different fields — cramming them together creates a messy, nullable-heavy schema.

**Option 2: Completely separate tables per role.**
This is the most common pattern and what I usually use. Each role has its own table with no shared structure. The downside is that cross-role relationships require polymorphic associations, which become slower over time and make some queries complex.

**Option 3: Shared root auth table + role-specific profile tables (chosen).**

I went with this approach. A minimal `users` table handles authentication and role identity. Each role has its own profile table (`agents`, `clients`) with role-specific fields. The trade-off is one extra join per query, but with modern SSDs and RAM, a single join is negligible.

The key advantage is extensibility. If a `manager` role is needed later, I create a `managers` table and add the role to the enum — done. The manager can relate to tickets via `manager_id` pointing to the user's root auth ID, just like agents do. Different roles can even have different auth mechanisms — agents use password, managers could use OTP, a future service account role could use API keys. I found that Microsoft uses similar patterns in some of their services. This gave me confidence in the choice.

## Step 4: Backend Implementation

Built on Laravel 13.

**Response structure:** I standardised all API responses using a custom `Response` utility class. The design is inspired by Facebook's approach (though they use GraphQL). The key feature is a `code` field alongside HTTP status codes. Why this matters: HTTP status codes alone aren't granular enough. For example, a 422 could be a validation error (show inline field errors) or a business rule violation like "insufficient balance" (show a modal). With a response code like `validation_failed` vs `not_enough_balance`, the frontend can make that distinction easily.

**Development flow:** I started with the simplest possible structure — routes, controllers, models, responses. No patterns, no abstractions. Once everything was working end-to-end, I refactored into a service layer. This "make it work, then make it right" approach let me move fast during the timebox without getting stuck on architecture upfront.

**Testing:** I used AI to help write the initial test suite, then reviewed each test to ensure it was actually testing something meaningful — not just asserting status codes, but verifying response structure, data correctness, and security boundaries. Tests that didn't add real value were removed.

## Step 5: Frontend Implementation

I chose SvelteKit. Here's why:

I wanted an API-first architecture with a standalone frontend, which ruled out Inertia. Vanilla Vue/React would require setting up routing, state management, and SSR configuration from scratch. Next.js and Nuxt.js include these out of the box, but their SSR configuration overhead is significant for a timeboxed project.

I looked for something that gives you routing and state management with minimal setup, and found SvelteKit. This was my first Svelte project(I don't even know how to pronounce it 😆). But having experienced the configuration overhead of Next.js and Nuxt.js, I was confident SvelteKit was the right choice for this timebox. After reading the documentation, I found it remarkably straightforward.

The frontend was largely built with AI assistance. This reflects modern development workflows — in my day job, I use AI for code that it can handle well, and focus my own time on architecture decisions, code review, and the parts that require domain understanding. Every AI-generated piece of code was reviewed and approved by me before committing.

## Step 6: Fine Tuning

After connecting frontend and backend with everything working end-to-end, I went back and refined both sides. I refactored where needed, added advanced filters, improved the SLA display, and polished the UI within the remaining time. I also added GitHub Actions for CI on the backend (didn't have time for the frontend). I integrated Spatie's activity log for ticket audit trails.

## What's Next

If I had more time, these are the areas I'd focus on:

### Auth System Performance Analysis

The multi-table auth model adds an extra join per request. I haven't load-tested this or measured the actual query overhead. In a production scenario, I'd benchmark the auth queries under load and optimise if needed (caching, eager loading, etc.).

### Notifications

When a ticket's status changes or a new message is posted, the client should receive an email notification and an in-app notification — similar to how Clickup handles updates. This would involve Laravel's notification system, likely with queued email delivery.

### Deployment

No deployment setup was created. In production, I'd configure Docker or Laravel Vapor, set up environment-specific configurations, and add health checks.

### SLA Command Scalability

The current `app:update-ticket-sla-status` command processes tickets in chunks of 1,000 with batch updates. For a dataset of 1 million open tickets, the command would likely take around 5 minutes — too long for the 2-minute schedule. The fix would be to dispatch the work to queued jobs (e.g., one job per chunk) so the command fans out immediately and workers process chunks in parallel. Alternatively, the SLA status could be computed on-read rather than stored, avoiding the batch update entirely.

### Activity Log

Currently using Spatie's `laravel-activitylog` which provides generic model-level logging. For a real ticketing system, I'd want a purpose-built ticket activity log — recording specific events like "priority changed from high to urgent by Agent X" or "ticket reassigned from Agent A to Agent B" with structured data, not just model diffs.

### Plan-Based SLA

SLA resolution times should be based on the organisation's plan tier, not just the ticket priority. For example, an "urgent" ticket for a free-plan org might have an 8-hour SLA, while the same priority for an enterprise-plan org gets 2 hours. The `SlaTimeGenerator` would need to accept both priority and plan as inputs.

### JSON:API Specification

The current response format is a custom envelope. For a production API, adopting the JSON:API specification would improve interoperability and give frontend developers a predictable contract. Not feasible within the timebox, but a clear next step.

### Soft Deletes

Models currently use hard deletes. In production, tickets, messages, and user records should use soft deletes to preserve audit trails and allow recovery.