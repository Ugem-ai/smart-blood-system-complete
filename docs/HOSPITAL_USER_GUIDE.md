# Hospital User Guide

## Registration and Approval

1. Register hospital account with contact details.
2. Wait for admin approval.
3. Login after approval.

## Hospital Dashboard

Main actions:

- Create blood request
- View request status list
- Review matched donors
- Confirm donation completion

## Creating Emergency Request

Required fields:

- blood type
- units required
- urgency level
- city (and optional coordinates)

After submission:

- Matching pipeline starts.
- Candidate donors are ranked.
- Notifications are sent.

## Confirming Donation

When donor accepts and donation is completed:

1. Confirm donation from hospital flow/API.
2. Donation record is saved.
3. Request status updates to completed.

## Access Control

- Hospital can only view sensitive donor contact/location for its own request scope.
