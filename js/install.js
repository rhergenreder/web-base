const NOT_STARTED = 0;
const PENDING = 1;
const SUCCESFULL = 2;
const ERROR = 3;

function setState(state) {
  let li = $("#currentStep");
  let icon, color, text;

  switch (state) {
    case PENDING:
      icon  = 'fas fa-spin fa-spinner';
      text  = "Loading…";
      color = "muted";
      break;

    case SUCCESFULL:
      icon  = 'fas fa-check-circle';
      text  = "Successfull";
      color = "success";
      break;

    case ERROR:
      icon  = 'fas fa-times-circle';
      text  = "Failed";
      color = "danger";
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
      statusBox.html("An error occurred during intallation: " + data.msg);
      statusBox.show();
    }
  }, "json").fail(function() {
    setState(ERROR);
    statusBox.addClass("alert-danger");
    statusBox.html("An error occurred during intallation. Try <a href=\"/index.php\">restarting the process</a>.");
    statusBox.show();
  }).always(function() {
    if(done) done(success);
  });
}

function retry() {
  $("#btnRetry").hide();
  $("#progressText").show();
  sendRequest({ }, function(success) {
    $("#progressText").hide();
    if(!success) $("#btnRetry").show();
  });
}

$(document).ready(function() {

  $("#btnSubmit").click(function() {
    params = { };
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
          setState(SUCCESFULL);
        }
      });
    });
  });

  $("#btnPrev").click(function() {
    $("#btnPrev").prop("disabled", true);
    sendRequest({ "prev": true }, function(success) {
      if(!success) {
        $("#btnPrev").prop("disabled",false);
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
  let prevPort = $("#port").val();
  let prevDbms = $("#type option:selected").val();
  function updateDefaultPort() {
    let defaultPorts = {
      "mysql": 3306,
      "postgres": 5432
    };

    let curDbms = $("#type option:selected").val();
    if(defaultPorts[prevDbms] === prevPort) {
      $("#port").val(defaultPorts[curDbms]);
    }
  }

  updateDefaultPort();
  $("#type").change(function() {
    updateDefaultPort();
  });
});
