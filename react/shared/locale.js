// application-wide global variables // translation cache
class Locale {

    constructor() {
        this.entries = {};
        this.currentLocale = "en_US";
    }

    translate(key) {

        if (this.currentLocale) {
            if (this.entries.hasOwnProperty(this.currentLocale)) {
                let [module, variable] = key.split(".");
                if (module && variable && this.entries[this.currentLocale].hasOwnProperty(module)) {
                    let translation = this.entries[this.currentLocale][module][variable];
                    if (translation) {
                        return translation;
                    }
                }
            }
        }

        return "[" + key + "]";
    }

    setLocale(code) {
        this.currentLocale = code;
        if (!this.entries.hasOwnProperty(code)) {
            this.entries[code] = {};
        }
    }

    loadModule(code, module, newEntries) {
        if (!this.entries.hasOwnProperty(code)) {
            this.entries[code] = {};
        }
        if (this.entries[code].hasOwnProperty(module)) {
            this.entries[code][module] = {...this.entries[code][module], ...newEntries};
        } else {
            this.entries[code][module] = newEntries;
        }
    }

    hasModule(code, module) {
        return this.entries.hasOwnProperty(code) && !!this.entries[code][module];
    }

    getModule(code, module) {
        if (this.hasModule(code, module)) {
            return this.entries[code][module];
        } else {
            return null;
        }
    }

    static getInstance() {
        return INSTANCE;
    }
}

let INSTANCE = new Locale();

function L(key) {
    return Locale.getInstance().translate(key);
}

export { L, Locale };
