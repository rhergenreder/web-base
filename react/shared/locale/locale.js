import LocaleGerman from "./german";
import LocaleEnglish from "./english";

let INSTANCE = new LocaleEnglish();

function initLocale(code) {
    if (!INSTANCE || INSTANCE.code !== code) {
        const constructors = {
            "de_DE": LocaleGerman,
            "en_US": LocaleEnglish,
        }

        if (constructors.hasOwnProperty(code)) {
            INSTANCE = new (constructors[code])();
        } else {
            INSTANCE = { code: code, entries: { } };
        }
    }

    return INSTANCE;
}

function translate(key) {
    return (INSTANCE.entries[key] || key);
}

function useLanguageModule(module) {
    if (module[INSTANCE.code]) {
        for (const [key, value] of Object.entries(module[INSTANCE.code])) {
            INSTANCE.entries[key] = value;
        }
    }
}

export  { translate as L, initLocale, useLanguageModule, INSTANCE as currentLocale };
