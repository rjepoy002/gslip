<!-- 🧾 Change Logs Modal -->
<div class="modal fade" id="changelogModal" tabindex="-1"
     aria-labelledby="changelogModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="changelogModalLabel">Change logs</h6>
        <button type="button" class="btn-close"
                data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body" style="font-size: 0.7rem;">
        <ol class="mb-0 ps-3">
          <li><strong>01/05/2026</strong> – Refactored main dashboard page to display gas slip records only and separated gas slip creation into a dedicated module.</li>

          <li><strong>02/16/2026</strong> – Fixed row counter duplication issue affecting destination and fuel item entries.</li>
          <li><strong>02/16/2026</strong> – Corrected fuel auto-computation logic to apply only to Diesel and Unleaded fuel items.</li>
          <li><strong>02/16/2026</strong> – Restricted fuel quantity fields to accept numeric and decimal values only.</li>
          <li><strong>02/16/2026</strong> – Improved fuel selection modal behavior by stabilizing row-specific targeting.</li>
          <li><strong>02/16/2026</strong> – Added automatic validity date generation for newly created fuel slip entries.</li>
          <li><strong>02/16/2026</strong> – Fixed row renumbering after adding or removing destinations and fuel items.</li>
          <li><strong>02/16/2026</strong> – Resolved issue preventing printed timestamp information from being saved to the database.</li>
          <li><strong>02/16/2026</strong> – Fixed sidebar toggle inconsistencies on print preview pages.</li>
          <li><strong>02/16/2026</strong> – Strengthened logout handling and session validation across secured pages.</li>

          <li><strong>03/01/2026</strong> – Added approved gas slip statistics and dashboard summary indicators.</li>
          <li><strong>03/05/2026</strong> – Implemented monthly and yearly gas slip analytics charts.</li>
          <li><strong>03/12/2026</strong> – Added department-based reporting and approval statistics.</li>
          <li><strong>03/18/2026</strong> – Introduced area-based reporting for departments with multiple operational areas.</li>

          <li><strong>05/03/2026</strong> – Enhanced dashboard visualizations with department overview charts.</li>
          <li><strong>05/10/2026</strong> – Improved chart legend and tooltip behavior for statistical reports.</li>
          <li><strong>05/14/2026</strong> – Updated department and area display logic based on user role and approval level.</li>
          <li><strong>05/21/2026</strong> – Added pagination support for large gas slip record listings.</li>
          <li><strong>05/22/2026</strong> – Improved user filtering based on assigned department and access permissions.</li>
          <li><strong>05/23/2026</strong> – Refined private approver workflows and department-area query logic.</li>
          <li><strong>05/24/2026</strong> – Improved reporting queries for faster dashboard generation.</li>
          <li><strong>05/28/2026</strong> – Optimized approval workflow processing and access control validation.</li>

          <li><strong>06/01/2026</strong> – Continued enhancements to dashboard performance, reporting tools, approval workflows, and user management integration.</li>
          <li><strong>06/04/2026</strong> – Added Top 10 Vehicles fuel consumption chart with role-based filtering.</li>
          <li><strong>06/05/2026</strong> – Added Top 10 Routes fuel consumption table.</li>
          <li><strong>06/08/2026</strong> – Added gas slip rejection workflow with rejection tracking.</li>
          <li><strong>06/09/2026</strong> – Implemented dashboard auto-refresh with idle detection and modal-aware protection.</li>
        </ol>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm"
                data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
