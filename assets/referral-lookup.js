/**
 * Referral Lookup Addon - Main JavaScript
 */

$(document).ready(function () {
  console.log(
    "Module link variable:",
    typeof moduleLink !== "undefined" ? moduleLink : "UNDEFINED"
  );

  let searchTimeout;
  let currentClientId = null;

  // Initialize Check Conflict button as disabled
  $("#checkConflictBtn").prop("disabled", true);

  // Search functionality
  $("#clientSearch").on("input", function () {
    console.log("Search input changed:", $(this).val());
    clearTimeout(searchTimeout);
    const term = $(this).val().trim();

    // Check if input is a valid email for conflict checking
    const isValidEmailInput = isValidEmail(term);
    $("#checkConflictBtn").prop("disabled", !isValidEmailInput);

    if (term.length >= 2) {
      searchTimeout = setTimeout(() => {
        console.log("Auto-search triggered for:", term);
        performSearch(term);
      }, 500);
    } else {
      hideResults();
    }
  });

  $("#clientSearch").on("keypress", function (e) {
    if (e.which === 13) {
      performSearch($(this).val().trim());
    }
  });

  $("#searchBtn").on("click", function () {
    console.log("Search button clicked");
    const searchTerm = $("#clientSearch").val().trim();
    console.log("Search term:", searchTerm);
    performSearch(searchTerm);
  });

  // Referral Conflict Checker
  $("#checkConflictBtn").on("click", function () {
    console.log("Check conflict button clicked");
    const clientEmail = $("#clientSearch").val().trim();
    console.log("Checking conflicts for email:", clientEmail);

    if (!clientEmail) {
      showAlert("Please enter a client email address", "warning");
      return;
    }

    if (!isValidEmail(clientEmail)) {
      showAlert("Please enter a valid email address", "warning");
      return;
    }

    showLoading();

    $.post(
      moduleLink,
      {
        action: "check_referral_conflicts",
        client_email: clientEmail,
      },
      function (response) {
        console.log("Conflict check response:", response);
        hideLoading();

        if (response.status === "success") {
          displayConflictResults(response);
        } else if (response.status === "not_found") {
          showConflictNotFound(clientEmail, response);
        } else {
          showAlert(response.message || "Conflict check failed", "danger");
        }
      },
      "json"
    ).fail(function (xhr, status, error) {
      console.error("Conflict check failed:", {
        xhr: xhr,
        status: status,
        error: error,
      });
      hideLoading();
      showAlert("Conflict check request failed", "danger");
    });
  });

  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  function displayConflictResults(response) {
    const client = response.client;
    const analysis = response.referral_analysis;

    // Update the results container title
    $("#resultsTitle").html(
      '<i class="fas fa-exclamation-triangle"></i> Enhanced Referral Conflict Analysis'
    );

    let html = `
      <div class="conflict-results">
        <div class="client-info">
          <h4>📋 Client Information</h4>
          <table class="info-table">
            <tr><td><strong>Name:</strong></td><td>${client.name}</td></tr>
            <tr><td><strong>Email:</strong></td><td>${client.email}</td></tr>
            <tr><td><strong>Company:</strong></td><td>${
              client.company || "N/A"
            }</td></tr>
            <tr><td><strong>Created:</strong></td><td>${
              client.created
            }</td></tr>
            <tr><td><strong>Status:</strong></td><td>${client.status}</td></tr>
          </table>
        </div>

        <div class="referral-analysis">
          <h4>🔍 Comprehensive Referral Analysis</h4>
          
          <!-- Analysis Summary -->
          <div class="analysis-summary">
            <h5>📊 Analysis Summary</h5>
            <div class="summary-stats">
              <div class="stat-item">
                <span class="stat-number">${
                  analysis.analysis_summary?.total_claims || 0
                }</span>
                <span class="stat-label">Total Claims</span>
              </div>
              <div class="stat-item">
                <span class="stat-number">${
                  analysis.analysis_summary?.unique_affiliates || 0
                }</span>
                <span class="stat-label">Unique Affiliates</span>
              </div>
              <div class="stat-item">
                <span class="stat-number">${
                  analysis.conflict_severity || "None"
                }</span>
                <span class="stat-label">Conflict Severity</span>
              </div>
            </div>
          </div>

          <!-- Status Display -->
          <div class="analysis-status ${
            analysis.conflict_detected ? "conflict" : "no-conflict"
          }">
            <strong>Status:</strong> ${analysis.conflict_message}
            ${
              analysis.conflict_severity
                ? `<br><small>Severity: ${analysis.conflict_severity}</small>`
                : ""
            }
          </div>

          <!-- All Referrers Section -->
          ${
            analysis.all_referrers && analysis.all_referrers.length > 0
              ? `
            <div class="all-referrers">
              <h5>👥 All Referral Claims</h5>
              <div class="referrers-list">
                ${analysis.all_referrers
                  .map(
                    (referrer, index) => `
                  <div class="referrer-item ${
                    referrer.type === "Database Referrer"
                      ? "database-referrer"
                      : "affiliate-claim"
                  }">
                    <div class="referrer-header">
                      <span class="referrer-type">${referrer.type}</span>
                      <span class="referrer-priority">Priority: ${
                        referrer.priority
                      }</span>
                    </div>
                    <div class="referrer-details">
                      <strong>${referrer.name}</strong> (${referrer.email})
                    </div>
                    <div class="referrer-source">
                      <small>Source: ${referrer.source}</small>
                    </div>
                    <div class="referrer-info">
                      <small>${referrer.details}</small>
                    </div>
                  </div>
                `
                  )
                  .join("")}
              </div>
            </div>
          `
              : ""
          }

          <!-- Additional Sources -->
          ${
            analysis.additional_sources &&
            analysis.additional_sources.length > 0
              ? `
            <div class="additional-sources">
              <h5>🔍 Additional Sources</h5>
              <ul>
                ${analysis.additional_sources
                  .map(
                    (source) => `
                  <li><strong>${source.type}:</strong> ${source.source} ${
                      source.value ? `- Value: ${source.value}` : ""
                    } ${source.count ? `(${source.count} items)` : ""}</li>
                `
                  )
                  .join("")}
              </ul>
            </div>
          `
              : ""
          }

          <!-- Database Referrer Info -->
          <div class="referrer-id-info">
            <h5>🗄️ Database Referrer ID</h5>
            <p><strong>Column Exists:</strong> ${
              analysis.has_referrer_id_column ? "Yes" : "No"
            }</p>
            <p><strong>Value:</strong> ${
              analysis.referrer_id_value || "None"
            }</p>
          </div>

          <!-- Recommendation -->
          <div class="recommendation">
            <h5>💡 Recommendation</h5>
            ${
              analysis.conflict_detected
                ? `<p class="warning">⚠️ <strong>CONFLICT DETECTED!</strong> Multiple affiliates are claiming this client. Manual review required before payment.</p>
                   <p class="info">📋 <strong>Action Required:</strong> Review all claims above and determine the legitimate referrer based on evidence and business rules.</p>`
                : analysis.analysis_summary?.total_claims > 0
                ? '<p class="info">✅ Single referral claim found. No conflicts detected.</p>'
                : '<p class="success">✅ No referral claims found. Client appears to be a direct registration.</p>'
            }
          </div>
        </div>
      </div>
    `;

    $("#resultsList").html(html);
    $("#searchResults").show();
    $("#noResults").hide();
  }

  function showConflictNotFound(email, response) {
    // Update the results container title
    $("#resultsTitle").html(
      '<i class="fas fa-exclamation-triangle"></i> Client Not Found'
    );

    let html = `
      <div class="conflict-not-found">
        <h4>❌ Client Not Found</h4>
        <p><strong>Email:</strong> ${email}</p>
        <p><strong>Message:</strong> ${response.message}</p>

        <div class="suggestions">
          <h5>💡 Suggestions:</h5>
          <ul>
            ${response.suggestions
              .map((suggestion) => `<li>${suggestion}</li>`)
              .join("")}
          </ul>
        </div>

        <div class="next-steps">
          <h5>🔍 Next Steps:</h5>
          <ol>
            <li>Verify the email address is correct</li>
            <li>Check if the client exists in a different database</li>
            <li>Check if the client was added after the database export</li>
            <li>Use the general search above to find similar clients</li>
          </ol>
        </div>
      </div>
    `;

    $("#resultsList").html(html);
    $("#searchResults").show();
    $("#noResults").hide();
  }

  function performSearch(term) {
    console.log("Performing search for term:", term);
    console.log("Module link:", moduleLink);

    if (term.length < 2) {
      showAlert("Please enter at least 2 characters", "warning");
      return;
    }

    showLoading();

    $.post(
      moduleLink,
      {
        action: "search_clients",
        term: term,
      },
      function (response) {
        console.log("Search response:", response);
        hideLoading();

        if (response.status === "success") {
          displayResults(response.data);
        } else {
          showAlert(response.message || "Search failed", "danger");
          hideResults();
        }
      },
      "json"
    ).fail(function (xhr, status, error) {
      console.error("AJAX failed:", { xhr: xhr, status: status, error: error });
      console.error("Response text:", xhr.responseText);
      hideLoading();
      showAlert("Search request failed", "danger");
    });
  }

  function displayResults(clients) {
    // Update the results container title
    $("#resultsTitle").html('<i class="fas fa-list"></i> Search Results');

    if (clients.length === 0) {
      showNoResults();
      return;
    }

    let html = `
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead class="thead-light">
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Company</th>
              <th>Status</th>
              <th>Created</th>
              <th>Referrer</th>
              <th>Match Type</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="resultsTableBody">
    `;

    clients.forEach(function (client) {
      const statusBadge = getStatusBadge(client.status);

      // Create match type badge
      const matchTypeBadge = getMatchTypeBadge(client.search_match_type);

      // Show referrer info if available
      let referrerInfo = "";
      let referrerBadge =
        '<span class="badge badge-secondary">No Referrer</span>';

      if (client.has_referrer === "Yes" && client.referrer_name) {
        referrerInfo = `
                    <br><small class="text-muted">
                        Referred by: ${escapeHtml(client.referrer_name)}
                        <span class="text-muted">(${escapeHtml(
                          client.referrer_email
                        )})</span>
                    </small>
                `;
        referrerBadge = '<span class="badge badge-success">Has Referrer</span>';
      }
      // Add affiliate indicator
      const affiliateIcon = client.is_affiliate
        ? `<i class="fas fa-star text-warning ml-1" title="Affiliate"></i>`
        : "";

      html += `
                <tr class="client-row" data-client-id="${client.id}">
                    <td><strong>#${client.id}</strong></td>
                    <td>
                        <strong>${escapeHtml(client.name)}</strong>
                        ${
                          client.has_referrer === "Yes"
                            ? `<i class="fas fa-user-friends text-success ml-1" title="Has referrer"></i>`
                            : ""
                        }
                        ${affiliateIcon}
                        ${referrerInfo}
                    </td>
                    <td>${escapeHtml(client.email)}</td>
                    <td>${escapeHtml(client.company || "-")}</td>
                    <td>${statusBadge}</td>
                    <td><small>${client.created}</small></td>
                    <td>${referrerBadge}</td>
                    <td>${matchTypeBadge}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewReferralDetails(${
                          client.id
                        })" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
    });

    html += `
          </tbody>
        </table>
      </div>
    `;

    $("#resultsList").html(html);
    $("#searchResults").show();
    $("#noResults").hide();

    // Add click handlers
    $(".client-row").on("click", function () {
      const clientId = $(this).data("client-id");
      viewReferralDetails(clientId);
    });
  }

  function viewReferralDetails(clientId) {
    console.log("Viewing referral details for client ID:", clientId);
    currentClientId = clientId;
    $("#referralModal").modal("show");

    $.post(
      moduleLink,
      {
        action: "get_referral_details",
        client_id: clientId,
      },
      function (response) {
        console.log("Referral details response:", response);
        if (response.status === "success") {
          displayReferralDetails(response);
        } else {
          console.error("Failed to load referral details:", response.message);
          $("#referralModalBody").html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        ${response.message || "Failed to load referral details"}
                    </div>
                `);
        }
      },
      "json"
    ).fail(function (xhr, status, error) {
      console.error("AJAX failed for referral details:", {
        xhr: xhr,
        status: status,
        error: error,
      });
      $("#referralModalBody").html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Failed to load referral details
                </div>
            `);
    });
  }

  function displayReferralDetails(data) {
    console.log("Displaying referral details with data:", data);

    // Check if required data exists
    if (!data.client) {
      console.error("Client data is missing:", data);
      $("#referralModalBody").html(`
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-triangle"></i>
          Invalid data structure received
        </div>
      `);
      return;
    }

    let html = `
            <div class="row">
                <div class="col-md-6">
                    <div class="referral-detail-section">
                        <h6 class="section-title"><i class="fas fa-user"></i> Client Information</h6>
                        <div class="detail-content">
                            <table class="table table-sm">
                                <tr><td><strong>ID:</strong></td><td>#${
                                  data.client.id
                                }</td></tr>
                                <tr><td><strong>Name:</strong></td><td>${escapeHtml(
                                  data.client.name
                                )}</td></tr>
                                <tr><td><strong>Company:</strong></td><td>${escapeHtml(
                                  data.client.company || "-"
                                )}</td></tr>
                                <tr><td><strong>Email:</strong></td><td>${escapeHtml(
                                  data.client.email
                                )}</td></tr>
                                <tr><td><strong>Status:</strong></td><td>${getStatusBadge(
                                  data.client.status
                                )}</td></tr>
                                <tr><td><strong>Created:</strong></td><td>${
                                  data.client.created
                                }</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
        `;

    if (data.referral_info.has_referrer && data.referral_info.referrer) {
      const referrer = data.referral_info.referrer;
      html += `
                <div class="referral-detail-section referral-success">
                    <h6 class="section-title"><i class="fas fa-user-friends"></i> Referred By</h6>
                    <div class="detail-content">
                        <table class="table table-sm">
                            <tr><td><strong>ID:</strong></td><td>#${
                              referrer.id
                            }</td></tr>
                            <tr><td><strong>Name:</strong></td><td>${escapeHtml(
                              referrer.name
                            )}</td></tr>
                            <tr><td><strong>Email:</strong></td><td>${escapeHtml(
                              referrer.email
                            )}</td></tr>
                            <tr><td><strong>Affiliate ID:</strong></td><td>#${
                              referrer.affiliate_id
                            }</td></tr>
                            <tr><td><strong>Service ID:</strong></td><td>#${
                              referrer.service_id
                            }</td></tr>
                            <tr><td><strong>Last Paid:</strong></td><td>${
                              referrer.last_paid || "Never"
                            }</td></tr>
                        </table>
                    </div>
                </div>
            `;
    } else {
      html += `
                <div class="referral-detail-section referral-secondary">
                    <h6 class="section-title"><i class="fas fa-user-minus"></i> No Referrer</h6>
                    <div class="detail-content text-center">
                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">This client was not referred by anyone</p>
                    </div>
                </div>
            `;
    }

    html += `</div></div>`;

    // Affiliate stats
    if (data.referral_info.affiliate_stats) {
      const stats = data.referral_info.affiliate_stats;
      html += `
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="referral-detail-section referral-warning">
                            <h6 class="section-title"><i class="fas fa-chart-line"></i> Affiliate Statistics</h6>
                            <div class="detail-content">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <h4 class="text-primary">${
                                          stats.total_referrals
                                        }</h4>
                                        <small class="text-muted">Total Referrals</small>
                                    </div>
                                    <div class="col-md-4">
                                        <h4 class="text-success">$${
                                          stats.total_commissions || "0.00"
                                        }</h4>
                                        <small class="text-muted">Total Commissions</small>
                                    </div>
                                    <div class="col-md-4">
                                        <h4 class="text-info">${
                                          stats.signup_date || "N/A"
                                        }</h4>
                                        <small class="text-muted">Signup Date</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
    }

    // Account statistics
    html += `
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="referral-detail-section">
                        <h6 class="section-title"><i class="fas fa-chart-bar"></i> Account Statistics</h6>
                        <div class="detail-content">
                            <div class="row text-center">
                                <div class="col-md-6">
                                    <h2 class="text-primary">${data.statistics.total_services}</h2>
                                    <small class="text-muted">Total Services</small>
                                </div>
                                <div class="col-md-6">
                                    <h2 class="text-info">${data.statistics.total_invoices}</h2>
                                    <small class="text-muted">Total Invoices</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

    $("#referralModalBody").html(html);
  }

  // View referral tree
  $("#viewReferralTree").on("click", function () {
    if (!currentClientId) {
      showAlert(
        "No client selected. Please view client details first.",
        "warning"
      );
      return;
    }

    // Show loading state
    $(this)
      .prop("disabled", true)
      .html('<i class="fas fa-spinner fa-spin"></i> Loading...');

    $.post(
      moduleLink,
      {
        action: "get_referral_tree",
        client_id: currentClientId,
      },
      function (response) {
        // Reset button
        $("#viewReferralTree")
          .prop("disabled", false)
          .html('<i class="fas fa-sitemap"></i> View Referral Tree');

        if (response && response.length > 0) {
          displayReferralTree(response);
        } else {
          // Show message in modal
          $("#referralModalBody").append(`
            <div class="alert alert-info mt-3">
              <i class="fas fa-info-circle"></i>
              <strong>No Referral Tree Found</strong><br>
              This client has not referred any other clients yet, or the referral system is not fully configured.
            </div>
          `);
        }
      },
      "json"
    ).fail(function (xhr, status, error) {
      // Reset button
      $("#viewReferralTree")
        .prop("disabled", false)
        .html('<i class="fas fa-sitemap"></i> View Referral Tree');

      console.error("Referral tree request failed:", {
        xhr: xhr,
        status: status,
        error: error,
      });

      // Show error in modal
      $("#referralModalBody").append(`
        <div class="alert alert-danger mt-3">
          <i class="fas fa-exclamation-triangle"></i>
          <strong>Error Loading Referral Tree</strong><br>
          Failed to load referral tree. Please try again.
        </div>
      `);
    });
  });

  function displayReferralTree(tree) {
    let html = `
            <div class="referral-detail-section mt-3">
                <h6 class="section-title"><i class="fas fa-sitemap"></i> Referral Tree (Multi-Level)</h6>
                <div class="detail-content">
        `;

    function renderTreeNode(nodes, level = 0) {
      let nodeHtml = "";
      nodes.forEach(function (node) {
        const indent = "&nbsp;".repeat(level * 4);
        const icon = level === 0 ? "fas fa-user" : "fas fa-user-plus";

        nodeHtml += `
                    <div class="mb-2">
                        ${indent}<i class="${icon} text-primary"></i>
                        <strong>#${node.id}</strong> ${escapeHtml(node.name)}
                        <small class="text-muted">(${node.created})</small>
                        ${
                          node.children.length > 0
                            ? `<span class="badge badge-info ml-2">${node.children.length} referrals</span>`
                            : ""
                        }
                    </div>
                `;

        if (node.children.length > 0) {
          nodeHtml += renderTreeNode(node.children, level + 1);
        }
      });
      return nodeHtml;
    }

    html += renderTreeNode(tree);
    html += `</div></div>`;

    $("#referralModalBody").append(html);
  }

  // Utility functions
  function showLoading() {
    $("#loadingSpinner").show();
    $("#searchResults").hide();
    $("#noResults").hide();
  }

  function hideLoading() {
    $("#loadingSpinner").hide();
  }

  function showNoResults() {
    $("#noResults").show();
    $("#searchResults").hide();
  }

  function hideResults() {
    $("#searchResults").hide();
    $("#noResults").hide();
  }

  function getStatusBadge(status) {
    const badges = {
      Active: "badge-success",
      Inactive: "badge-warning",
      Closed: "badge-danger",
    };
    const badgeClass = badges[status] || "badge-secondary";
    return `<span class="badge ${badgeClass}">${status}</span>`;
  }

  function getMatchTypeBadge(matchType) {
    const badges = {
      client: { class: "badge-primary", text: "Client", icon: "fas fa-user" },
      domain: { class: "badge-info", text: "Domain", icon: "fas fa-globe" },
      custom_field: {
        class: "badge-warning",
        text: "Custom",
        icon: "fas fa-tag",
      },
    };

    const badge = badges[matchType] || {
      class: "badge-secondary",
      text: "Other",
      icon: "fas fa-question",
    };
    return `<span class="badge ${badge.class}" title="Matched by ${badge.text}">
                    <i class="${badge.icon}"></i> ${badge.text}
                </span>`;
  }

  function getDomainStatusBadge(status) {
    const badges = {
      Active: "badge-success",
      Pending: "badge-warning",
      "Pending Transfer": "badge-info",
      Expired: "badge-danger",
      Cancelled: "badge-secondary",
      Fraud: "badge-danger",
    };
    const badgeClass = badges[status] || "badge-secondary";
    return `<span class="badge ${badgeClass}">${status}</span>`;
  }

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function showAlert(message, type) {
    const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `;

    // Remove existing alerts
    $(".alert").remove();

    // Add new alert at the top
    $(".card-body").first().prepend(alertHtml);

    // Auto dismiss after 5 seconds
    setTimeout(function () {
      $(".alert").fadeOut();
    }, 5000);
  }

  // Make viewReferralDetails globally accessible
  window.viewReferralDetails = viewReferralDetails;
});
