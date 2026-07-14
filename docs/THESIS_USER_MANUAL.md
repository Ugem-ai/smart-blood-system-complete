# Smart Blood System - Unified User Manual (Thesis Edition)

## Document Control

- Document Title: Unified User Manual
- System: Smart Blood System for Emergency Blood Donation Coordination
- Version: 1.0
- Date: 2026-07-14
- Intended Audience: Donors, Hospital Staff, and System Administrators
- Prepared For: Thesis Documentation and System Evaluation

## 1. Overview

The Smart Blood System is a web-based platform that coordinates emergency blood donation by matching hospital blood requests with eligible donors using the PAST-Match ranking process.

This manual consolidates operational guidance for all major user roles:

- Donor users
- Hospital users and staff
- Administrative users

## 2. Objectives of the Manual

This manual aims to:

- Explain account setup and access requirements
- Provide role-specific workflows for routine and emergency use
- Define key rules, permissions, and security responsibilities
- Offer troubleshooting and support guidance suitable for thesis documentation

## 3. User Roles and Responsibilities

### 3.1 Donor

Donors maintain personal eligibility and respond to incoming blood donation requests.

Primary responsibilities:

- Keep profile data accurate (blood type, city, contact details)
- Enable or disable availability based on real status
- Review incoming requests and respond promptly
- Observe medical and policy constraints for donation frequency

### 3.2 Hospital User / Hospital Staff

Hospital users create and manage blood requests, review ranked donor matches, issue notifications, and confirm completed donations.

Primary responsibilities:

- Submit complete and accurate blood requests
- Prioritize emergency requests correctly
- Track donor responses and coordinate collection
- Confirm completed donations in the system

### 3.3 Administrator

Administrators govern access, approve hospital accounts, monitor platform activity, and enforce compliance and security controls.

Primary responsibilities:

- Review and approve hospital registrations
- Supervise usage and operational metrics
- Audit logs and monitor suspicious behavior
- Enforce security and credential management practices

## 4. Access and Login

### 4.1 General Access

- Access is role-based and requires authenticated login.
- Users can only view and operate within their allowed scope.

### 4.2 Login Endpoints

- Donor login: Use donor login page in the main authentication flow
- Hospital login: Hospital dashboard authentication route
- Admin login: Administrative authentication route

If login fails:

- Verify username/email and password
- Check account approval status (for hospital users)
- Confirm internet and browser compatibility
- Contact IT support for account reset or lock review

## 5. Donor User Procedures

### 5.1 Account Setup

1. Register as a donor.
2. Provide blood type, city, and contact details.
3. Accept privacy consent.
4. Complete profile and health-related information as required.

### 5.2 Donor Dashboard Functions

Main donor dashboard functions include:

- Profile update
- Availability toggle
- Incoming blood requests
- Donation history
- Coordination and acceptance status

### 5.3 Responding to Donation Requests

1. Open incoming request details.
2. Review blood type, urgency, location, and expected response window.
3. Select Accept or Decline.
4. Monitor response status on the donor dashboard.

### 5.4 Donor Eligibility Rules

- Minimum donation interval: 56 days
- Availability must be enabled to receive matching requests
- Matching depends on blood type compatibility and policy filters

### 5.5 Donor Best Practices

- Keep blood type and city information current.
- Set availability status accurately.
- Respond quickly to emergency notifications.
- Update contact details to avoid missed alerts.

## 6. Hospital User Procedures

### 6.1 Registration and Approval

1. Register hospital account with required contact details.
2. Wait for administrative approval.
3. Log in after approval to access hospital features.

### 6.2 Creating a Blood Request

1. Go to hospital request module.
2. Enter blood type, required units, urgency, and location.
3. Confirm and submit the request.
4. System queues matching process and returns operational status.

### 6.3 Monitoring Request Progress

Hospital users can:

- View matched donors and response status
- Track pending, matching, fulfilled, or cancelled requests
- Monitor emergency mode indicators
- Review travel acceptance and coordination data

### 6.4 Updating Request Status

Allowed operations include:

- Mark request as in progress
- Mark request as fulfilled when completed
- Cancel request when no longer needed

Status changes should be applied only by authorized users with valid operational basis.

### 6.5 Emergency Workflow

When urgency is high or critical:

- Ensure request details are complete and accurate
- Use emergency fields and operational guidance
- Coordinate rapidly with matched donors
- Confirm completion promptly to release resources

## 7. Administrator Procedures

### 7.1 Hospital Approval Management

Administrators review hospital submissions and can:

- Approve qualified facilities
- Reject or request correction for incomplete records
- Revoke access when required by policy

### 7.2 Dashboard Monitoring

Admin dashboard includes:

- Active requests and status trends
- Matching and response metrics
- Notification reliability indicators
- System operational mode visibility

### 7.3 User and Access Governance

Administrators manage:

- Role assignment and account state
- Security reviews and policy enforcement
- Audit and compliance checks

### 7.4 Emergency Broadcast Control

Administrators can:

- Activate or deactivate emergency broadcast mode
- Monitor escalation windows and alerts
- Verify disaster-response operational behavior

## 8. Core Functional Modules

### 8.1 PAST-Match Donor Ranking

The PAST-Match process ranks candidates based on multi-factor suitability, including compatibility, proximity, and operational criteria.

### 8.2 Notification and Escalation

The system supports:

- Primary and fallback channels
- Retry logic for failed deliveries
- Escalation windows for urgent requests

### 8.3 Coordination Safeguards

To avoid conflicting allocations:

- Reserved donors are protected from duplicate assignment
- Expired reservations are released by policy timing
- Cancelled requests release donor reservation locks

### 8.4 Chapter and Inventory Features

Where enabled, chapter inventory and transfer workflows support:

- Cross-chapter stock visibility
- Transfer recommendations
- Centralized operational oversight

## 9. Security and Privacy Guidance

### 9.1 Data Protection

- Personal data must be used only for authorized operations.
- Access must follow least-privilege principles.
- Session and credential handling must follow institutional policy.

### 9.2 Password and Account Safety

- Use strong passwords and avoid reuse.
- Do not share credentials.
- Report suspicious activity immediately.

### 9.3 Audit and Traceability

- Critical actions should be logged and auditable.
- Admin actions and status transitions should be reviewable.

## 10. Troubleshooting

### 10.1 Login Problems

Possible causes:

- Invalid credentials
- Unapproved hospital account
- Locked or deactivated account

Actions:

- Reset password
- Verify role and approval
- Contact administrator

### 10.2 No Donor Matches Found

Possible causes:

- Strict filters or short distance radius
- Low donor availability
- Incompatible blood type pool

Actions:

- Review urgency and location settings
- Recheck request data quality
- Allow broader operational radius as policy permits

### 10.3 Notification Delays

Possible causes:

- Temporary channel outage
- Delivery retries in progress
- Invalid contact or device token data

Actions:

- Check notification status logs
- Validate contact fields
- Re-run emergency escalation if required

### 10.4 Request Not Updating

Possible causes:

- Permission mismatch
- Stale browser session
- Validation or workflow rule conflict

Actions:

- Refresh session and retry
- Confirm role permissions
- Review validation message and status constraints

## 11. Operational Best Practices

- Keep donor and hospital data updated.
- Use clear and correct urgency labels.
- Close completed requests quickly.
- Monitor dashboards daily during active operations.
- Perform regular audit and backup routines.

## 12. Thesis Evaluation Notes

This user manual is intended to support:

- Demonstration of role-based workflows
- Usability and operational correctness assessment
- Reproducibility of core emergency request scenarios
- Documentation completeness for thesis defense

## 13. Support and Maintenance

For technical concerns and system maintenance:

- Coordinate with project maintainers and IT administrator
- Record incidents with timestamp, role, and action details
- Apply updates through controlled release procedures

## 14. Revision History

- v1.0 (2026-07-14): Restored and standardized unified thesis user manual for donor, hospital, and admin workflows.
