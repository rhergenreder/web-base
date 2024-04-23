let Core = function () {

  this.__construct = function () {
    this.url = document.location.href;
    this.parseParameters();
  };

  this.apiCall = function (func, params, callback) {
    params = typeof params !== 'undefined' ? params : {};
    callback = typeof callback !== 'undefined' ? callback : function (data) {};
    let config = { method: "POST" };

    if (params instanceof FormData) {
      config.body = params;
    } else {
      config.headers = { "Content-Type": "application/json" };
      config.body = JSON.stringify(params);
    }

    const self = this;
    const path = '/api' + (func.startsWith('/') ? '' : '/') + func;
    fetch(path, config).then((data) => {
      data.json().then(data => {
        callback.call(self, data);
      }).catch(reason => {
        console.log("API-Function Error: " + reason);
        callback.call(self, {success: false, msg: "An error occurred. API-Function: " + reason });
      })
    }).catch(reason => {
      console.log("API-Function Error: " + reason);
      callback.call(self, {success: false, msg: "An error occurred. API-Function: " + reason });
    });
  };

  this.getCookie = function (cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(";");
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
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
    return this.parameters;
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
    if (typeof this.parameters[param] !== 'undefined' && this.parameters.hasOwnProperty(param)) {
      delete this.parameters[param];
    }
    this.updateUrl();
  };

  this.getParameter = function (param) {
    if (typeof this.parameters[param] !== 'undefined' && this.parameters.hasOwnProperty(param))
      return this.parameters[param];
    else
      return null;
  };

  this.setParameter = function (param, newValue) {
    newValue = typeof newValue !== 'undefined' ? newValue : '';
    this.parameters[param] = newValue;
    this.updateUrl();
  };

  this.parseParameters = function () {
    this.parameters = [];
    if (this.url.indexOf('?') === -1)
      return;

    let paramString = this.url.substring(this.url.indexOf('?') + 1);
    let split = paramString.split('&');
    for (let i = 0; i < split.length; i++) {
      let param = split[i];
      let index = param.indexOf('=');
      if (index !== -1) {
        this.parameters[param.substring(0, index)] = param.substring(index + 1);
      } else
        this.parameters[param] = '';
    }
  };

  this.updateUrl = function () {
    this.clearUrl();
    let i = 0;
    for (let parameter in this.parameters) {
      this.url += (i === 0 ? "?" : "&") + parameter;
      if (this.parameters.hasOwnProperty(parameter) && this.parameters[parameter].toString().length > 0) {
        this.url += "=" + this.parameters[parameter];
      }
      i++;
    }
  };

  this.clearParameters = function () {
    this.parameters = [];
    this.updateUrl();
  };

  this.clearUrl = function () {
    if (this.url.indexOf('#') !== -1) {
      this.url = this.url.substring(0, this.url.indexOf('#'));
    }
    if (this.url.indexOf('?') !== -1) {
      this.url = this.url.substring(0, this.url.indexOf('?'));
    }
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

  this.getCaptchaProvider = function () {
    return window.captchaProvider || null;
  }

  this.__construct();
};

let jsCore = new Core();

function L(key, defaultTranslation=null) {

  let entries = window.languageEntries || {};
  let [module, variable] = key.split(".");
  if (module && variable && entries.hasOwnProperty(module)) {
    let translation = entries[module][variable];
    if (translation) {
      return translation;
    }
  }

  return defaultTranslation || "[" + key + "]";
}