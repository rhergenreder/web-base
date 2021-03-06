let Core = function () {

  this.__construct = function () {
    this.url = document.location.href;
    this.parseParameters();
  };

  this.apiCall = function (func, params, callback) {
    params = typeof params !== 'undefined' ? params : {};
    callback = typeof callback !== 'undefined' ? callback : function (data) {};

    const path = '/api' + (func.startsWith('/') ? '' : '/') + func;
    $.post(path, params, function (data) {
      console.log(func + "(): success=" + data.success + " msg=" + data.msg);
      callback.call(this, data);
    }, "json").fail(function (jqXHR, textStatus, errorThrown) {
      let msg = func + " Status: " + textStatus + " error thrown: " + errorThrown;
      console.log("API-Function Error: " + msg);
      callback.call(this, {success: false, msg: "An error occurred. API-Function: " + msg });
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

  this.__construct();
};

let jsCore = new Core();