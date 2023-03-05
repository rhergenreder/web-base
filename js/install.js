const NOT_STARTED = 0;
const PENDING = 1;
const SUCCESSFUL = 2;
const ERROR = 3;

let currentState = PENDING;

function setState(state) {
  let li = $("#currentStep");
  let icon, color, text;
  currentState = state;

  switch (state) {
    case PENDING:
      icon  = 'fas fa-spin fa-spinner';
      text  = "Loading…";
      color = "muted";
      break;

    case SUCCESSFUL:
      icon  = 'fas fa-check-circle';
      text  = "Successful";
      color = "success";
      break;

    case ERROR:
      icon  = 'fas fa-times-circle';
      text  = "Failed";
      color = "danger";
      $("#btnRetry").show();
      break;

    default:
      icon = 'far fa-circle';
      text = "Pending";
      color = "muted";
      break;
  }

  li.find("small").removeClass().addClass("text-" + color).html(text);
  li.find("span").removeClass().addClass("text-" + color);
  li.find("i").removeClass().addClass(icon);
}

function getCurrentStep() {
  return $("#currentStep").index() + 1;
}

function requestCurrentStep(callback) {
  $.post("/index.php?status", {}, function(data) {
    callback(data.step ?? null);
  }, "json").fail(function() {
    callback(null);
  });
}

function sendRequest(params, done) {
  setState(PENDING);
  let success = false;
  let statusBox = $("#status");

  statusBox.hide();
  $.post("/index.php", params, function(data) {
    if(data.success || data.step !== getCurrentStep()) {
      success = true;
      window.location.reload();
    } else {
      setState(ERROR);
      statusBox.addClass("alert-danger");
      statusBox.html("An error occurred during installation: " + data.msg);
      statusBox.show();
    }
  }, "json").fail(function() {
    setState(ERROR);
    statusBox.addClass("alert-danger");
    statusBox.html("An error occurred during installation. Try <a href=\"/index.php\">restarting the process</a>.");
    statusBox.show();
  }).always(function() {
    if(done) done(success);
  });
}

function retry() {
  let progressText = $("#progressText");
  let wasHidden = progressText.hasClass("hidden");
  $("#btnRetry").hide();
  progressText.removeClass("hidden");
  sendRequest({ }, function(success) {
    if (wasHidden) {
      $("#progressText").addClass("hidden");
    }
    if(!success) {
      $("#btnRetry").show();
    }
  });
}

function waitForStatusChange() {
  setTimeout(() => {
    requestCurrentStep((step) => {
      if (currentState === PENDING) {
        if (step !== 2 || step == null) {
          document.location.reload();
        } else {
          waitForStatusChange();
        }
      }
    })
  }, 2500);
}

$(document).ready(function() {

  $("#btnSubmit").click(function() {
    let params = { };
    let submitButton = $("#btnSubmit");
    let textBefore = submitButton.text();
    submitButton.prop("disabled", true);
    submitButton.html("Submitting… <i class=\"fas fa-spinner fa-spin\">");
    $("#installForm .form-control").each(function() {
      let type = $(this).attr("type") ?? $(this).prop("tagName").toLowerCase();
      let name = $(this).attr("name");
      if(type === "text") {
        params[name] = $(this).val().trim();
      } else if(type === "password" || type === "number") {
        params[name] = $(this).val();
      } else if(type === "select") {
        params[name] = $(this).find(":selected").val();
      }
    }).promise().done(function() {
      sendRequest(params, function(success) {
        if(!success) {
          submitButton.prop("disabled",false);
          submitButton.text(textBefore);
        } else {
          setState(SUCCESSFUL);
        }
      });
    });
  });

  $("#btnPrev").click(function() {
    $("#btnPrev").prop("disabled", true);
    sendRequest({ "prev": true }, function(success) {
      if(!success) {
        $("#btnPrev").prop("disabled", false);
      } else {
        window.location.reload();
      }
    });
  });

  $("#btnSkip").click(function() {
    $("#btnSkip").prop("disabled", true);
    sendRequest({ "skip": true }, function(success) {
      if(!success) {
        $("#btnSkip").prop("btnSkip",false);
      } else {
        window.location.reload();
      }
    });
  });

  $("#btnFinish").click(function() {
    window.location = "/admin";
  });

  $("#btnRetry").click(function() {
    retry();
  });

  // DATABASE PORT
  let portField = $("#port");
  let typeField = $("#type");

  let prevPort = parseInt(portField.val());
  let prevDbms = typeField.find("option:selected").val();
  function updateDefaultPort() {
    let defaultPorts = {
      "mysql": 3306,
      "postgres": 5432
    };

    let curDbms = typeField.find("option:selected").val();
    if(defaultPorts[prevDbms] === prevPort) {
      prevDbms = curDbms;
      portField.val(prevPort = defaultPorts[curDbms]);
    }
  }

  updateDefaultPort();
  typeField.change(function() {
    updateDefaultPort();
  });

  // INSTALL_DEPENDENCIES ?
  if (getCurrentStep() === 2) {
    sendRequest({}, () => {
      waitForStatusChange();
    });
  }
});
