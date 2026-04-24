                # Implemented System Audit Checklist for Thesis Defense

                ## Smart Blood Donation Monitoring and Matching System
                ## Philippine Red Cross Alignment

                ## Purpose

                This checklist is designed for a formal audit of the already-implemented Smart Blood Donation Monitoring and Matching System. It is structured for thesis defense, pilot-readiness review, and operational assurance activities. The checklist verifies whether the implemented system enforces safe blood requisition workflows, protects donor and patient data, preserves auditability, and applies the PAST-Match algorithm in a transparent and fair manner.

                ## Audit Use Instructions

                For each item, the auditor should record one of the following outcomes against the live implementation:

                | Rating | Meaning |
                |---|---|
                | Compliant | Control is already implemented, enforced, and evidenced by code, tests, configuration, or runtime behavior. |
                | Partially Compliant | Control is implemented but shows gaps in enforcement, coverage, or evidence. |
                | Non-Compliant | Control is absent, bypassable, or not evidenced in the implemented system. |
                | Not Applicable | Control is outside the deployment scope being audited. |

                ## Audit Positioning

                This document does not propose new controls. It is an auditor-facing verification matrix for controls that are already present, claimed as present, or expected to be demonstrable from the current implementation. The objective is to prove operational compliance and identify any remaining gaps between implementation, enforcement, and evidentiary readiness for defense.

                ## Recommended Evidence Sources

                - API route protection and middleware configuration
                - Validation rules for blood request submission and authentication
                - Activity log records and audit monitoring views
                - PAST-Match algorithm logic, tests, and explainability output
                - Queue worker, HTTPS, monitoring, and deployment configuration
                - Functional, security, and performance test cases

                ---

                ## A. Procedural Compliance with Blood Requisition Workflows

                | ID | Requirement | Current Implemented Control to Verify | How to Test or Validate |
                |---|---|---|---|
                | PROC-01 | Only approved hospital accounts may initiate blood requisitions. | The system must block request creation when the hospital profile is pending, rejected, suspended, or missing. Approval must be an administrative prerequisite before operational use. | Attempt to create a request using an unapproved hospital account. Confirm the API or UI returns a denial response and records the event. Review hospital approval records and login restrictions. |
                | PROC-02 | Every requisition must capture minimum operational details before submission. | The request form must require blood type, units required, urgency level, city or location context, and distance limit. Optional fields such as contact person and expiry may be accepted, but the request must not proceed with clinically incomplete core data. | Submit requests with each required field omitted one at a time. Confirm validation errors are specific, complete, and prevent persistence. Test invalid blood types, units outside accepted bounds, and malformed contact numbers. |
                | PROC-03 | Request creation must automatically trigger donor matching without manual intervention. | Upon valid submission, the system must create a request in a controlled initial status and dispatch a matching job to the queue. Staff should not need to run separate steps to activate matching. | Create a valid request and verify that a matching job is dispatched, the request appears in the hospital request list, and matched donors become available within the defined processing window. |
                | PROC-04 | Request lifecycle must follow the intended operational sequence. | A standard request should move through a controlled sequence such as pending, matching, accepted or completed coordination, then fulfilled or cancelled. Status transitions must reflect actual operational events, not manual convenience changes. | Run end-to-end tests from request creation to donor acceptance and donation confirmation. Verify that status changes occur only when corresponding events happen. Attempt to force an out-of-order status update and confirm it is blocked. |
                | PROC-05 | Donor confirmation by hospital must only occur after donor acceptance. | The hospital must not be able to confirm a donation for a donor who never accepted the request. Acceptance should be a documented prerequisite for confirmation. | Attempt to confirm a donation for a donor with no accepted response. Confirm the operation is rejected and no donation history record is created. |
                | PROC-06 | Cancelled requests must stop further coordination activity. | When a request is cancelled, pending notifications should be halted, reserved donors should be released, and the request must not continue through matching or fulfillment. | Cancel an active request and verify that follow-up notifications stop, donor allocation is released, and the request cannot be fulfilled afterward without reactivation through an authorized process. |
                | PROC-07 | Emergency requests must use an explicit emergency path with heightened controls. | Critical or emergency requests should trigger expedited matching, expanded radius or broadcast logic where configured, and operational monitoring distinct from normal cases. Emergency handling must still preserve traceability and approval rules. | Create an emergency request and verify faster prioritization, emergency-mode indicators, monitoring traces, and complete audit logging. Confirm that emergency mode does not bypass validation or authentication. |
                | PROC-08 | No available donor scenarios must be handled safely and transparently. | If no eligible donor exists, the request must remain visible to staff, preserve its pending state or defined escalation state, and surface a clear operational outcome rather than failing silently. | Use test data with no compatible donors, ineligible donors, or all donors unavailable. Confirm the request remains trackable and the system communicates zero-match status clearly. |
                | PROC-09 | Duplicate or conflicting donor coordination across hospitals must be prevented. | Once a donor is actively allocated to another request, a second hospital must not be allowed to confirm or reserve that same donor concurrently without explicit release logic. | Create competing requests from two hospitals targeting the same eligible donor. Accept the first request and verify the second hospital receives a conflict response or donor exclusion. |
                | PROC-10 | Manual fallback procedures must exist for system degradation. | Because emergency blood operations cannot wait for full platform recovery, a documented fallback process must exist for network loss, notification failure, or system outage. | Review incident response documentation and simulate a degraded scenario such as notification failure or temporary outage. Verify that staff can continue using a defined fallback donor-contact process. |

                ---

                ## B. Data Integrity and Validation Enforcement

                | ID | Requirement | Current Implemented Control to Verify | How to Test or Validate |
                |---|---|---|---|
                | DATA-01 | Blood type data must be normalized and restricted to valid transfusion categories. | Input should be normalized to canonical blood group values and rejected if not in the approved compatibility set. | Submit lowercase, mixed-case, legacy, and invalid blood type values. Confirm valid values are normalized and invalid values are rejected. |
                | DATA-02 | Units requested must stay within safe and configured bounds. | The system must reject zero, negative, non-numeric, and excessive unit counts. Upper bounds should reflect operational policy and prevent implausible or abusive requests. | Test values such as 0, -1, 1, 20, and 21. Verify only valid quantities are persisted. |
                | DATA-03 | Date fields must preserve temporal consistency. | Required date and expiry date must follow valid chronology. Expiry must not precede the required date. | Submit requests with expiry before required date, invalid dates, and null dates where optional. Confirm chronological validation works correctly. |
                | DATA-04 | Geographic input must be validated before use in matching. | Latitude and longitude must remain inside valid geographic ranges. Missing coordinates should trigger defined fallback behavior rather than corrupting distance calculations. | Submit out-of-range coordinates, missing coordinates, and valid coordinates. Confirm invalid coordinates are rejected and same-city fallback behavior works when coordinates are absent. |
                | DATA-05 | Emergency flags and urgency values must be consistent. | The system must derive emergency behavior from urgency and operational mode rules instead of trusting arbitrary client values alone. | Submit combinations of normal urgency plus emergency flag and critical urgency without flag. Verify the resolved emergency state matches business rules. |
                | DATA-06 | Donor eligibility data must stay current and enforce the minimum donation interval. | Matching must exclude donors whose last donation is within the prohibited interval, regardless of manual availability status. | Seed donors with donation intervals below and above 56 days. Verify ineligible donors are excluded from matching. |
                | DATA-07 | Privacy consent must be mandatory for donor onboarding. | Donor registration must not succeed unless privacy consent is explicitly accepted and timestamped. | Attempt donor registration with and without consent. Confirm refusal without consent and persistence of consent timestamp when accepted. |
                | DATA-08 | Registration data must prevent account collisions and ambiguous identity. | User email must be unique. Hospital-only registration controls such as invite codes or approved domains must be enforced to reduce fraudulent enrollment. | Attempt duplicate registration, invalid hospital domain registration, expired invite code registration, and valid invite code registration. Confirm only the authorized path succeeds. |
                | DATA-09 | Matching and response records must stay internally consistent. | Request matches, donor responses, allocation records, and donation histories must reflect the same donor-request relationship without orphaned or contradictory states. | Accept, decline, confirm, cancel, and re-run matching on the same request. Validate referential consistency across the related tables and API responses. |
                | DATA-10 | System must reject malformed or incomplete API submissions gracefully. | Invalid JSON, missing required fields, and incorrect types must return controlled errors without partially writing data. | Send malformed payloads and type violations directly to the API. Confirm the response is deterministic, no partial rows are created, and logs capture the failed attempt where appropriate. |

                ---

                ## C. Role-Based Access Control Implementation

                | ID | Requirement | Current Implemented Control to Verify | How to Test or Validate |
                |---|---|---|---|
                | RBAC-01 | Access must be segregated by donor, hospital, and admin role. | Protected routes must require authentication and role-specific middleware so each role can only access its own functional domain. | Attempt cross-role API access, such as donor-to-hospital routes and hospital-to-admin routes. Confirm 403 responses and denial logging. |
                | RBAC-02 | Unauthorized role access attempts must be audit logged. | Every blocked access attempt should generate an activity log entry with actor role, target path, method, IP, and reason. | Trigger role violations intentionally and inspect audit logs for complete denial metadata. |
                | RBAC-03 | Administrative functions must be limited to explicitly approved administrators. | Only admin accounts should be able to approve hospitals, modify settings, access audit dashboards, control emergency mode, or manipulate PAST-Match settings. | Verify all admin endpoints reject donor and hospital tokens. Confirm admin-only settings and monitoring routes are inaccessible to lower roles. |
                | RBAC-04 | Hospital users must be scoped to their own institution’s requests. | A hospital must not view, edit, cancel, confirm, or list blood requests belonging to another hospital unless the action is routed through a clearly authorized central-admin function. | Use one hospital account to request another hospital’s request identifier. Confirm the system denies access and does not reveal detailed data. |
                | RBAC-05 | Donor actions must be limited to the donor’s own requests and profile. | Donors should only accept or decline requests addressed to them and should only update their own availability or profile data. | Attempt to submit donor responses on behalf of another donor by manipulating identifiers. Confirm the system binds the action to the authenticated donor profile only. |
                | RBAC-06 | Monitoring endpoints must not become a side channel for unrestricted access. | Health and metrics endpoints should use scoped exposure, tokens, or infrastructure restrictions so they do not disclose operational detail to unauthorized parties. | Access monitoring endpoints with and without the required token or network context. Confirm unauthorized access is rejected. |
                | RBAC-07 | Deactivated or unapproved users must not retain operational capability. | Suspension, rejection, or pending approval states must immediately limit operational actions even if a user still holds credentials. | Attempt login and protected operations using a suspended donor or pending hospital account. Confirm access is blocked consistently. |
                | RBAC-08 | Least privilege must be enforced inside the interface, not only at the API. | UI elements, dashboards, navigation menus, and action buttons should reflect the current role so users are not presented with forbidden actions. | Log in under each role and verify the UI does not expose unauthorized modules. Then test API bypass attempts to confirm server-side enforcement remains authoritative. |

                ---

                ## D. Security Mechanisms: Application, Database, and Network

                | ID | Requirement | Current Implemented Control to Verify | How to Test or Validate |
                |---|---|---|---|
                | SEC-01 | Authentication must use secure credential handling. | Passwords must be hashed, tokens must be issued through an authenticated mechanism, and login failures must avoid user enumeration. | Inspect the authentication flow, then test valid login, invalid password, and non-existent user responses. Confirm errors remain generic and passwords are never stored in plaintext in the user table. |
                | SEC-02 | Protected endpoints must require authenticated tokens. | All donor, hospital, and admin APIs must reject missing, invalid, or expired tokens. | Test each route group without a token, with an invalid token, and with an expired token. Confirm 401 responses. |
                | SEC-03 | Authentication abuse must be rate limited. | Login and public endpoints should be throttled to reduce brute force or enumeration attacks. Sensitive operational routes should also use request-rate control. | Perform repeated login attempts and repeated unauthenticated registration or password-reset requests. Confirm throttling activates and the system remains responsive. |
                | SEC-04 | Sensitive fields must be encrypted or otherwise protected at rest. | Donor contact numbers and hospital addresses or contact details should use field-level encryption or equivalent database protection. Backup files must also be encrypted. | Inspect data model casts or storage controls, review backup procedures, and confirm plaintext exposure is not present in normal database inspection or exported backups. |
                | SEC-05 | Transport security must be mandatory in production. | HTTP traffic must redirect to HTTPS. The deployment must enforce modern TLS and strong security headers. | Send HTTP requests to the production endpoint and confirm redirection to HTTPS. Inspect TLS protocol support and response headers. |
                | SEC-06 | Application security headers must reduce common browser attack surfaces. | Responses should include HSTS, frame restrictions, content-type sniffing protection, and related baseline headers. | Inspect production response headers from the web gateway and verify that required headers are present consistently. |
                | SEC-07 | Network exposure must be minimized to operational ports only. | Public access should be limited to HTTPS and necessary web ports. Administrative SSH access should be restricted to approved addresses. Database and Redis should not be exposed publicly unless formally secured. | Review cloud firewall or security group rules and confirm only approved inbound services are exposed. Scan the deployment host from an external network. |
                | SEC-08 | Monitoring and integration secrets must be protected. | Tokens for metrics, SMS, push notifications, and integrations must be stored as secrets, not hard-coded into source or client-visible payloads. | Review environment management, secret distribution, and API responses. Confirm that production secrets are injected securely and not leaked in logs or front-end bundles. |
                | SEC-09 | Security incidents must have an operational response path. | The organization must maintain documented procedures for outages, notification failure, login issues, and suspected breaches, including contact escalation. | Review incident-response documentation and run a tabletop exercise for one security and one availability incident. |
                | SEC-10 | Database resilience controls must preserve safety under load or failure. | Connection pooling, backups, recovery objectives, and queue-based degradation should prevent silent loss of requests during spikes or partial outages. | Simulate high concurrency, database delay, and queue backlogs. Confirm requests are queued or deferred safely and can be recovered from backup when needed. |

                ---

                ## E. Audit Trail Completeness and Immutability

                | ID | Requirement | Current Implemented Control to Verify | How to Test or Validate |
                |---|---|---|---|
                | AUD-01 | High-risk operational actions must always generate audit records. | Registration, login success and failure, unauthorized access attempts, request creation, donor access, matching control actions, and donation confirmation must all be logged. | Execute representative actions and verify the corresponding audit log entries are present, searchable, and timestamped. |
                | AUD-02 | Audit entries must identify who did what, when, and to what target. | Logs should include actor identity, role, action, timestamp, target object, request path where relevant, and outcome or severity. | Review multiple log samples across authentication, matching, and access events to confirm metadata completeness. |
                | AUD-03 | Audit logging must include blocked and failed events, not only successful actions. | Security-relevant failures such as unauthorized role access and invalid login attempts must be visible to auditors. | Intentionally generate blocked actions and failed logins. Confirm they appear in the audit stream with appropriate severity. |
                | AUD-04 | Audit logs must be queryable for compliance review. | Administrators should be able to filter audit data by date, actor role, category, and action type without altering the underlying records. | Open the audit dashboard or reporting endpoint and verify filter behavior, export function, and timeline views. |
                | AUD-05 | Audit logs must be protected against tampering or silent deletion. | The preferred design is append-only storage with restricted write paths, database permissions that deny routine updates or deletes, and backup retention for forensic review. | Review database privileges, model behaviors, admin endpoints, and operational procedures. Attempt to alter or delete an audit entry using ordinary application permissions. |
                | AUD-06 | Audit clocks must be reliable and aligned. | All nodes generating logs should use synchronized time so event reconstruction is defensible during incident review. | Verify NTP or time synchronization on application, queue, and database hosts. Compare timestamps for the same transaction across components. |
                | AUD-07 | Sensitive personal data must not be overexposed in logs. | Logs should capture identifiers and risk-relevant context but avoid unnecessary storage of full secrets, full contact details, or full patient narratives. | Inspect audit log payloads for registration, request viewing, and donor access. Confirm no sensitive values are unnecessarily retained. |
                | AUD-08 | Audit review must support anomaly detection. | The audit layer should enable identification of repeated unauthorized access, elevated failure rates, unusual emergency overrides, and repeated matching re-runs. | Review summary views, alerts, or query patterns that surface high-risk events. Simulate repeated violations and confirm they are visible to administrators. |

                ---

                ## F. Workflow Enforcement and Prevention of Unauthorized Transitions

                | ID | Requirement | Current Implemented Control to Verify | How to Test or Validate |
                |---|---|---|---|
                | FLOW-01 | The system must prevent users from skipping the matching stage. | A request should not move directly from initial submission to fulfilled unless matching and donor acceptance prerequisites are satisfied or a formally authorized exception is recorded. | Attempt to update a fresh request directly to fulfilled or completed through the API or UI. Confirm rejection unless the approved workflow preconditions exist. |
                | FLOW-02 | Only valid role-holders may trigger each transition. | Hospitals may create and confirm within scope, donors may accept or decline their own invitations, and admins may perform only their authorized supervisory controls. | Attempt each transition with the wrong role token. Confirm server-side denial. |
                | FLOW-03 | Transition logic must be state-aware. | The system should define allowed transitions from each status and reject any status change that is not in the permitted transition map. | Test transitions such as cancelled to fulfilled, pending to completed without donor acceptance, and fulfilled back to matching. Confirm invalid paths are blocked. |
                | FLOW-04 | Repeated donor responses must not create contradictory states. | Duplicate accept or decline actions should update idempotently or be rejected with a clear message, without creating duplicate response records. | Submit repeated accept and decline calls for the same donor-request pair. Verify one authoritative response record remains. |
                | FLOW-05 | Donor reservation and release must be consistent with request state. | Accepting a request should reserve the donor for that request. Cancelling the request or ending the coordination should release the donor appropriately. | Use multi-hospital tests to verify reserve, conflict, cancel, and release behavior. |
                | FLOW-06 | Emergency overrides must remain controlled and logged. | Radius expansion, emergency-mode activation, notification pause or resume, and matching re-runs must require authorized actors and produce audit records. | Trigger each control from an authorized admin account and verify that the change takes effect and is logged. Attempt the same control from a hospital or donor account and confirm denial. |
                | FLOW-07 | Failed downstream services must not cause silent workflow corruption. | If SMS, push delivery, or queue processing fails, the request state should remain coherent and retriable instead of advancing as if delivery succeeded. | Simulate notification failure or queue interruption. Confirm workflow state remains accurate and recovery actions are possible. |
                | FLOW-08 | The user interface must not rely on client-side rules alone for workflow safety. | Buttons and screens may guide users, but the API must enforce all workflow rules independently. | Use direct API calls to bypass the interface and attempt prohibited transitions. Confirm back-end validation blocks them. |

                ---

                ## G. Privacy Protection and Separation of Patient and Donor Data

                | ID | Requirement | Current Implemented Control to Verify | How to Test or Validate |
                |---|---|---|---|
                | PRIV-01 | Donor and patient data must be logically separated. | The requisition workflow should collect only the patient context necessary to source blood while storing donor identity and donor eligibility data in separate entities and access scopes. | Review data models, request payloads, and UI screens to confirm patient-specific details are not embedded into donor profile records or broad donor-facing views. |
                | PRIV-02 | Minimum necessary disclosure must govern hospital access to donor data. | Hospitals should receive only the donor information needed to coordinate donation, such as name and approved contact channel, not unrestricted personal profile detail or excessive location precision unless justified. | Inspect matched-donor payloads and hospital screens. Confirm that fields disclosed to hospitals align with operational necessity and institutional privacy policy. |
                | PRIV-03 | Donors must not see patient-identifying information unless explicitly authorized by policy. | Donor notifications should reveal request urgency and collection location as needed while minimizing patient-identifying details. | Review donor notification content, incoming request views, and API payloads. Confirm patient names, diagnoses, or unrelated medical data are absent unless formally required and justified. |
                | PRIV-04 | Privacy consent must be demonstrable and time-stamped. | The system must record when donor consent was obtained and must prevent processing for operational matching before consent exists. | Verify donor records include consent timestamps and that non-consenting accounts cannot be onboarded into the donor pool. |
                | PRIV-05 | Sensitive fields returned by APIs must be role-appropriate and masked where possible. | Hidden model fields, response transformers, or access policies should prevent unnecessary exposure of contact numbers, emails, exact coordinates, and internal identifiers. | Compare raw model definitions with actual API responses for donor, hospital, and admin roles. Confirm hidden or masked fields remain protected. |
                | PRIV-06 | Logs and exports must preserve confidentiality. | Audit exports, reports, and analytics should avoid unnecessary full disclosure of personal data and should be accessible only to authorized reviewers. | Export reports and audit logs, then inspect whether personal data is minimized and access-controlled. |
                | PRIV-07 | Backup and recovery processes must preserve privacy protections. | Restored data must retain encryption, role boundaries, and access controls; emergency recovery should not require insecure shared files unless formally governed. | Review backup encryption, restore procedures, and fallback artifacts such as manual donor lists. Confirm those artifacts are access-controlled and updated responsibly. |
                | PRIV-08 | Data sharing with external or national systems must be explicitly governed. | Any integration with external partners should require admin authorization, purpose limitation, and traceable synchronization records. | Review integration endpoints, admin-only controls, and logs for partner synchronization. Confirm no uncontrolled external data exposure exists. |

                ---

                ## H. Matching Algorithm Integrity and Fairness: PAST-Match

                | ID | Requirement | Current Implemented Control to Verify | How to Test or Validate |
                |---|---|---|---|
                | ALG-01 | Only eligible donors may enter the candidate pool. | PAST-Match must filter out donors who are incompatible by blood type, unavailable, within the prohibited donation interval, or outside the applicable radius unless a documented fallback rule applies. | Seed donors across each exclusion condition and verify only eligible donors remain in the ranked list. |
                | ALG-02 | Base compatibility scoring must remain explainable and stable. | The algorithm should compute a normalized base audit score from defined grouped factors and configurable weights. This score should remain inspectable for audit review. | Generate a match explanation and verify the factor groups, weights, and base score are visible and mathematically consistent. |
                | ALG-03 | Emergency prioritization must be distinct from baseline compatibility. | Emergency adjustments may reorder donors for operational urgency, but the baseline audit score should remain preserved for explainability and post-event review. | Compare donor ranking with and without emergency mode. Confirm the base score is unchanged while the operational ranking changes where expected. |
                | ALG-04 | Fairness rotation must prevent repeated overuse of the same donors. | Donors matched recently should receive a controlled operational penalty so high-reliability donors do not monopolize every request. | Create donors with equal base scores but different last-matched times. Confirm recent donors drop in operational rank according to the cooldown tiers. |
                | ALG-05 | Tie-breaking must be deterministic. | Identical scores must resolve in a consistent, documented way so repeated runs on the same inputs produce the same order. | Run the same request repeatedly against identical donor conditions and confirm stable ordering. |
                | ALG-06 | Geographic fallback behavior must not unfairly exclude viable donors. | When precise donor coordinates are missing but donor and request share the same city, the system may estimate location with reduced confidence instead of discarding the donor outright. | Remove coordinates from otherwise eligible donors in the same city and verify they remain in the candidate list with reduced confidence signaling. |
                | ALG-07 | Reliability scoring must support response effectiveness without becoming discriminatory. | Reliability may improve ranking, but it must not override hard eligibility, fairness rotation, or clinical compatibility. | Compare donors with high reliability but poor availability or recent donation against donors with valid eligibility. Confirm reliability does not rescue ineligible donors. |
                | ALG-08 | No available donor scenarios must leave an auditable trail. | A zero-match result should be visible in logs, request state, and escalation monitoring so staff can explain why no donor was produced. | Run a request with no eligible donors and inspect system state, monitoring output, and audit logs. |
                | ALG-09 | Algorithm controls must be limited to authorized administrators. | Weight changes, emergency-mode controls, radius expansion, re-runs, and notification pauses must not be available to hospitals or donors. | Attempt to access PAST-Match detail and control endpoints with non-admin roles. Confirm denial. |
                | ALG-10 | Performance must remain within emergency-use thresholds. | Matching should complete within the declared operational target for realistic donor volumes so fairness and integrity controls remain usable in real emergencies. | Execute performance tests at representative donor counts such as 10, 100, and 1,000 donors. Confirm runtime remains within the documented threshold. |
                | ALG-11 | Algorithm outputs must be reproducible for defense and audit. | Given the same data, same urgency profile, and same system settings, PAST-Match should yield the same ranked output and explanation unless emergency context or donor state changed. | Freeze test data, settings, and urgency level, then run the algorithm multiple times. Confirm identical outputs. |
                | ALG-12 | Algorithm changes must be governed and reviewable. | Any adjustment to weights, urgency profiles, or control logic should be limited to authorized administrators, logged, and included in change control records. | Modify PAST-Match settings in an authorized environment and verify the change is logged, reviewable, and attributable to a named actor. |

                ---

                ## Edge Case Validation Matrix

                | Scenario | Audit Expectation |
                |---|---|
                | Emergency request during disaster mode | Matching accelerates, emergency controls activate, logging remains complete, and normal validation is not bypassed. |
                | No available donors | Request remains visible and traceable, no silent failure occurs, and escalation or fallback action can be initiated. |
                | Invalid or incomplete submission | Request is rejected before persistence, the user receives clear validation feedback, and no partial workflow side effects occur. |
                | Unauthorized access attempt | Access is blocked, no protected data is disclosed, and a security audit event is recorded. |
                | Donor accepts while already allocated elsewhere | System returns conflict, protects cross-hospital coordination integrity, and preserves prior allocation. |
                | Network interruption during critical coordination | System degrades safely, queued work is preserved where possible, and manual fallback procedures are available. |
                | Notification service failure | Request state remains coherent, retry or fallback is possible, and staff are not misled into assuming successful delivery. |
                | Matching re-run after changing emergency settings | The system records who changed the controls, why the re-run occurred, and what matching context was used. |

                ---

                ## Auditor Focus Points for Thesis Defense

                The following points deserve explicit attention during defense because they are often questioned by panel members and operational reviewers:

                1. Workflow-state enforcement must be demonstrated live or through test evidence. It is not enough to show that statuses exist; the system must prove that invalid transitions are rejected.
                2. Audit logging presence is not equivalent to audit immutability. The defense should explain whether logs are append-only by design, protected by database permissions, retained in backups, and shielded from ordinary modification.
                3. Privacy-by-design should be demonstrated at the API response level, not only in database storage. The panel may ask whether hospitals are exposed only to the minimum donor data required for coordination.
                4. PAST-Match fairness should be defended as both effective and ethically controlled. The strongest explanation is that clinical compatibility and urgency remain primary, while cooldown penalties prevent repeated overuse of the same reliable donors.
                5. Emergency operations should be presented as controlled exceptions, not uncontrolled bypasses. Every emergency override must remain authenticated, logged, and reviewable after the event.

                ## Preliminary Repository-Based Audit Observations

                These observations are based on the current repository review and should be validated during formal audit execution:

                1. Workflow transition control requires special scrutiny. The request update path accepts multiple status values, so the audit should verify that invalid status jumps cannot be performed in practice through exposed endpoints or administrative tooling.
                2. Audit logging is present and reasonably rich, but immutability is not yet proven by repository evidence alone. The panel may reasonably ask whether database permissions, append-only retention controls, or external archival measures prevent retroactive alteration.
                3. Privacy minimization should be checked at the matched-donor response level. The hospital-facing donor payload appears operationally rich, so the audit should verify whether each disclosed field is necessary under minimum-necessary data handling principles.
                4. Emergency and administrative overrides appear to be logged, which is a strength, but the audit should confirm that override use is periodically reviewed and not treated as a routine operating shortcut.

                ## Suggested Audit Conclusion Format

                At the end of the formal audit, summarize findings using the following structure:

                1. Overall compliance rating
                2. Critical non-compliance findings
                3. High-risk partial compliance areas
                4. Controls verified as effective
                5. Required corrective actions before deployment or pilot expansion
                6. Recommended follow-up evidence for thesis panel review
