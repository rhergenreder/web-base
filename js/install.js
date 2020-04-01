const NOT_STARTED = 0;
const PENDING = 1;
const SUCCESFULL = 2;
const ERROR = 3;

function setState(state) {
  var li = $("#currentStep");
  var icon, color, text;

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

function sendRequest(params, done) {
  setState(PENDING);
  var success = false;
  $("#status").hide();
  $.post("/index.php", params, function(data) {
    if(data.success) {
      success = true;
      window.location.reload();
    } else {
      setState(ERROR);
      $("#status").addClass("alert-danger");
      $("#status").html("An error occurred during intallation: " + data.msg);
      $("#status").show();
    }
  }, "json").fail(function() {
    setState(ERROR);
    $("#status").addClass("alert-danger");
    $("#status").html("An error occurred during intallation. Try <a href=\"/index.php\">restarting the process</a>.");
    $("#status").show();
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
    var textBefore = $("#btnSubmit").text();
    $("#btnSubmit").prop("disabled", true);
    $("#btnSubmit").html("Submitting… <i class=\"fas fa-spinner fa-spin\">");
    $("#installForm .form-control").each(function() {
      var type = $(this).attr("type") ?? $(this).prop("tagName").toLowerCase();
      var name = $(this).attr("name");
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
          $("#btnSubmit").prop("disabled",false);
          $("#btnSubmit").text(textBefore);
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
    window.location.reload();
  });

  $("#btnRetry").click(function() {
    retry();
  });

  // DATABASE PORT
  var prevPort = $("#port").val();
  var prevDbms = $("#type option:selected").val();
  function updateDefaultPort() {
    var defaultPorts = {
      "mysql": 3306,
      "postgres": 5432,
      "oracle": 1521
    };

    var curDbms = $("#type option:selected").val();
    if(defaultPorts[prevDbms] == prevPort) {
      $("#port").val(defaultPorts[curDbms]);
    }
  }

  updateDefaultPort();
  $("#type").change(function() {
    updateDefaultPort();
  });
});
