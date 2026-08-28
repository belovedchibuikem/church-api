# Workflow Catalog

All transitions are server-side state machines. Privileged transitions record actor, source/target state, target, scope, timestamp, reason where applicable, and correlation ID.

- **Home Church:** Draft → Submitted → Under Review → Interview/Orientation → Approved → Active; alternatives Rejected, Suspended, Closed.
- **Need Request:** Submitted → Reviewed → Approved → Assigned → Fulfilled → Closed.
- **Crusade Invitation:** Received → Under Review → Approved → Planning → Confirmed → Completed.
- **KCA Application:** Received → Reviewed → Accepted / Provisionally Accepted / Deferred / Not Accepted.
- **KCA Assignment:** Draft → Assigned → Submitted → Mentor Review → Resubmit / Approved / Needs Attention → Admin Review → Final Assessment.
- **Press Publication:** Manuscript → Editorial Review → Theological Review → Copy Editing → Design → ISBN → Publication Approval → Published → Distribution.
- **Payment Intent:** Pending Provider → Succeeded / Failed / Cancelled / Expired. Verified successful transactions are immutable and feed separate reconciliation, receipt, refund-request, and dispute records; provider/governance defaults deny.
- **Press Translation:** Machine Generated → Under Review → Reviewed → Approved.
- **Communication Broadcast:** Draft → Prepared; cancellation remains a controlled alternative. Prepared audiences snapshot eligible/suppressed recipients before local notification or provider-neutral delivery attempts.
- **Alert Occurrence:** Open → Acknowledged → Resolved. The same unresolved rule/condition fingerprint is deduplicated; a resolved condition may recur as a new occurrence.
- **Data Subject Request:** the implemented export path is Pending Review → Processing → Completed → Expired; Approved, Rejected, and Failed are reserved states pending the reviewed governance workflow. Execution remains default-denied pending OD-007.
- **Safeguarding:** Guardian relationships begin Pending; incident and restricted-read transitions remain policy-controlled and default deny where governance is unresolved.
- **Prayer:** Submitted → Routed/Assigned → In Progress → Responded → Closed.

The listed state machines are implemented where corresponding models/actions exist, except legacy catalogue-only Need Request, Prayer, and broader pastoral workflows. Thresholds, authorities, provider decisions, and jurisdictional rules that are not source-defined remain in `OPEN_DECISIONS.md`.
