# Workflow Catalog

All transitions are server-side state machines. Privileged transitions record actor, source/target state, target, scope, timestamp, reason where applicable, and correlation ID.

- **Home Church:** Draft → Submitted → Under Review → Interview/Orientation → Approved → Active; alternatives Rejected, Suspended, Closed.
- **Need Request:** Submitted → Reviewed → Approved → Assigned → Fulfilled → Closed.
- **Crusade Invitation:** Received → Under Review → Approved → Planning → Confirmed → Completed.
- **KCA Application:** Received → Reviewed → Accepted / Provisionally Accepted / Deferred / Not Accepted.
- **KCA Assignment:** Draft → Assigned → Submitted → Mentor Review → Resubmit / Approved / Needs Attention → Admin Review → Final Assessment.
- **Press Publication:** Manuscript → Editorial Review → Theological Review → Copy Editing → Design → ISBN → Publication Approval → Published → Distribution.
- **Payment:** Initiated → Pending → Successful / Failed / Cancelled; successful transactions may enter Refunded or Disputed through controlled workflows.
- **Prayer:** Submitted → Routed/Assigned → In Progress → Responded → Closed.

Thresholds, authorities, and jurisdictional rules that are not source-defined remain in `OPEN_DECISIONS.md`.
