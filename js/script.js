let Core = function () {

  this.__construct = function () {
    this.url = document.location.href;
    this.parseParameters();
    this.langEntries = {};
  };

  this.apiCall = function (func, aParams, callback, onerror) {
    aParams = typeof aParams !== 'undefined' ? aParams : {};
    callback = typeof callback !== 'undefined' ? callback : function (data) {};

    onerror = typeof onerror !== 'undefined' ? onerror : function (msg) {
      bootbox.alert("An error occurred: " + msg);
    };

    const path = '/api' + (func.startsWith('/') ? '' : '/') + func;
    $.post(path, aParams, function (data) {
      console.log(func + "(): success=" + data.success + " msg=" + data.msg);
      if (data.hasOwnProperty('logoutIn') && $("#logoutTimer").length > 0) {
        $("#logoutTimer").attr("data-time", data.logoutIn);
      }

      if (!data.success) {
        onerror.call(this, data.msg);
      } else {
        callback.call(this, data);
      }
    }, "json").fail(function (jqXHR, textStatus, errorThrown) {
      console.log("API-Function Error: " + func + " Status: " + textStatus + " error thrown: " + errorThrown);
      onerror.call(this, "An error occurred. API-Function: " + func + " Status: " + textStatus + " - " + errorThrown);
    });
  };

  this.getCookie = function (cname) {
    var name = cname + "=";
    var decodedCookie = decodeURIComponent(document.cookie);
    var ca = decodedCookie.split(";");
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) === ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) === 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
  };

  this.addLangEntry = function (key, val) {
    this.langEntries[key] = val;
  };

  this.getLangEntry = function (key) {
    if (typeof this.langEntries[key] !== 'undefined' && this.langEntries.hasOwnProperty(key)) {
      return this.langEntries[key];
    }

    return key;
  };

  this.getUrl = function () {
    return this.url;
  };

  this.getParameters = function () {
    return this.aParameters;
  };

  this.setTitle = function (title) {
    document.title = title;
  };

  this.changeURL = function (history) {
    if (history) {
      window.history.pushState({
        "html": document.getElementsByTagName("body")[0].innerHTML,
        "pageTitle": document.title
      }, "", this.url);
    } else {
      window.history.replaceState({
        "html": document.getElementsByTagName("body")[0].innerHTML,
        "pageTitle": document.title
      }, "", this.url);
    }
  };

  this.redirect = function () {
    window.location = this.url;
  };

  this.reload = function () {
    window.location.reload();
  };

  this.removeParameter = function (param) {
    if (typeof this.aParameters[param] !== 'undefined' && this.aParameters.hasOwnProperty(param)) {
      delete this.aParameters[param];
    }
    this.updateUrl();
  };

  this.getParameter = function (param) {
    if (typeof this.aParameters[param] !== 'undefined' && this.aParameters.hasOwnProperty(param))
      return this.aParameters[param];
    else
      return null;
  };

  this.setParameter = function (param, newvalue) {
    newvalue = typeof newvalue !== 'undefined' ? newvalue : '';
    this.aParameters[param] = newvalue;
    this.updateUrl();
  };

  this.parseParameters = function () {
    this.aParameters = [];
    if (this.url.indexOf('?') === -1)
      return;

    var paramString = this.url.substring(this.url.indexOf('?') + 1);
    var split = paramString.split('&');
    for (var i = 0; i < split.length; i++) {
      var param = split[i];
      var index = param.indexOf('=');
      if (index !== -1) {
        var key = param.substr(0, index);
        var val = param.substr(index + 1);
        this.aParameters[key] = val;
      } else
        this.aParameters[param] = '';
    }
  };

  this.updateUrl = function () {
    this.clearUrl();
    let i = 0;
    for (var parameter in this.aParameters) {
      this.url += (i === 0 ? "?" : "&") + parameter;
      if (this.aParameters.hasOwnProperty(parameter) && this.aParameters[parameter].toString().length > 0) {
        this.url += "=" + this.aParameters[parameter];
      }
      i++;
    }
  };

  this.clearParameters = function () {
    this.aParameters = [];
    this.updateUrl();
  };

  this.clearUrl = function () {
    if (this.url.indexOf('?') !== -1)
      this.url = this.url.substring(0, this.url.indexOf('?'));
  };

  this.logout = function () {
    this.apiCall('user/logout');
  };

  this.getJsonDateTime = function (date) {
    return date.getFullYear() + "-" +
        ((date.getMonth() + 1 < 10) ? "0" : "") + (date.getMonth() + 1) + "-" +
        (date.getDate() < 10 ? "0" : "") + date.getDate() + " " +
        (date.getHours() < 10 ? "0" : "") + date.getHours() + "-" +
        (date.getMinutes() < 10 ? "0" : "") + date.getMinutes() + "-" +
        (date.getSeconds() < 10 ? "0" : "") + date.getSeconds();
  };

  this.getJsonDate = function (date) {
    return this.getJsonDateTime(date).split(' ')[0];
  };

  this.getJsonTime = function (date) {
    return this.getJsonDateTime(date).split(' ')[1];
  };

  this.showInputDialog = function (title, aInputs, callback, element, onCreated) {
    title = typeof title !== "undefined" ? title : "";
    aInputs = typeof aInputs !== "undefined" ? aInputs : {};
    callback = typeof callback !== "undefined" ? callback : function (aResult, element) {
    };
    onCreated = typeof onCreated !== "undefined" ? onCreated : function () {
    };

    var html = '<div class="modal-header"><h4 class="modal-title">' + title + '</h4></div>' +
        '<form class="bootbox-form">';

    for (var i in aInputs) {
      var input = aInputs[i];

      if (input.type !== "hidden" && input.type !== "checkbox")
        html += '<label for="' + input.name + '">' + input.name + ':</label>';

      if (input.type === "select") {
        html += '<select id="' + input.id + '" class="bootbox-input bootbox-input-select form-control">';

        var aValues = (input.hasOwnProperty("aValues") && typeof input.aValues !== "undefined") ? input.aValues : {};
        for (var value in aValues) {
          var name = aValues[value];
          var selected = (input.value === value) ? " selected" : "";
          html += '<option value="' + value + '"' + selected + '>' + name + '</option>';
        }

        html += '</select>';
        input.type = "select";
      } else if (input.type === "checkbox") {
        html += '<div class="checkbox">' +
            '<label><table><tr>' +
            '<td style="vertical-align:top;padding-top:3px;">' +
            '<input class="bootbox-input bootbox-input-checkbox" id="' + input.id + '" value="1" type="checkbox"' + (input.value ? " checked" : "") + '>' +
            '</td>' +
            '<td style="padding-left: 5px;">' + input.text + '</td>' +
            '</tr></table></label>' +
            '</div>';
      } else if (input.type === "date") {
        html += '<input class="bootbox-input form-control customDatePicker" autocomplete="off" ' +
            'type="text" ' +
            'name="' + input.name + '" ' +
            'id="' + input.id + '" ' +
            'value="' + (input.value ? input.value : "") + '"' + (input.readonly ? " readonly" : "") +
            (input.maxlength ? ' maxlength="' + input.maxlength + '"' : '') + '>';
      } else if (input.type === "time") {
        html += '<div class="input-group">' +
            '<input class="bootbox-input" autocomplete="off" value="0" pattern="[0-9][0-9]" type="number" name="' + input.name + '" id="' + input.id + 'Hour" style="width:60px;text-align: center">' +
            '<span>:</span>' +
            '<input class="bootbox-input" autocomplete="off" type="number" name="' + input.name + '" id="' + input.id + 'Minute" value="00" style="width:60px;text-align: center">' +
            '</div>';
      } else {
        html += '<input class="bootbox-input form-control" autocomplete="off" ' +
            'type="' + input.type + '" ' +
            'name="' + input.name + '" ' +
            'id="' + input.id + '" ' +
            'value="' + (input.value ? input.value : "") + '"' + (input.readonly ? " readonly" : "") +
            (input.maxlength ? ' maxlength="' + input.maxlength + '"' : '') + '>';
      }
    }

    html += '</form>';
    var dialog = bootbox.confirm(html, function (result) {
      if (result) {
        var aResult = [];
        for (var i in aInputs) {
          var input = aInputs[i];
          var value = $("#" + input.id).val();

          if (input.type === "select")
            value = $("#" + input.id).find(":selected").val();
          else if (input.type === "checkbox")
            value = $("#" + input.id).prop("checked");

          aResult[input.id] = value;
        }
        callback.call(this, aResult, element);
      }
    });

    dialog.init(function () {
      $(".modal-body").on("keypress", "input", function (e) {
        if (e.keyCode === 13) {
          e.preventDefault();
          $(".modal-footer .btn-primary").click();
        }
      });
      onCreated.call(this);
    });
  };

  this.__construct();
};

let jsCore = new Core();
$(document).ready(function() {

});

function createLoadingIcon() {
  return '<i class="fas fa-spin fa-spinner"></i>';
}