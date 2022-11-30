import React from 'react';
import {createContext, useCallback, useState} from "react";

const LocaleContext = React.createContext(null);

function LocaleProvider(props) {

    const [entries, setEntries] = useState(window.languageEntries || {});
    const [currentLocale, setCurrentLocale] = useState(window.languageCode || "en_US");

    const translate = useCallback((key) => {
        if (currentLocale) {
            if (entries.hasOwnProperty(currentLocale)) {
                let [module, variable] = key.split(".");
                if (module && variable && entries[currentLocale].hasOwnProperty(module)) {
                    let translation = entries[currentLocale][module][variable];
                    if (translation) {
                        return translation;
                    }
                }
            }
        }

        return "[" + key + "]";
    }, [currentLocale, entries]);

    const loadModule = useCallback((code, module, newEntries) => {
        let _entries = {...entries};
        if (!_entries.hasOwnProperty(code)) {
            _entries[code] = {};
        }
        if (_entries[code].hasOwnProperty(module)) {
            _entries[code][module] = {..._entries[code][module], ...newEntries};
        } else {
            _entries[code][module] = newEntries;
        }
        setEntries(_entries);
    }, [entries]);

    const loadModules = useCallback((code, modules) => {
        setEntries({...entries, [code]: { ...entries[code], ...modules }});
    }, [entries]);

    const hasModule = useCallback((code, module) => {
        return entries.hasOwnProperty(code) && !!entries[code][module];
    }, [entries]);

    const getModule = useCallback((code, module) => {
        if (hasModule(code, module)) {
            return entries[code][module];
        } else {
            return null;
        }
    }, [entries]);

    /** API HOOKS **/
    const setLanguage = useCallback(async (api, params) => {
        let res = await api.setLanguage(params);
        if (res.success) {
            setCurrentLocale(res.language.code)
        }
        return res;
    }, []);

    const setLanguageByName = useCallback((api, name) => {
        return setLanguage(api, {name: name});
    }, [setLanguage]);

    const setLanguageByCode = useCallback((api, code) => {
        return setLanguage(api, {code: code});
    }, [setLanguage]);

    const requestModules = useCallback(async (api, modules, code=null, useCache=true) => {
        if (!Array.isArray(modules)) {
            modules = [modules];
        }

        if (code === null) {
            code = currentLocale;
            if (code === null && api.loggedIn) {
                code = api.user.language.code;
            }
        }

        if (code === null) {
            return { success: false, msg: "No locale selected currently" };
        }

        let languageEntries = {};
        if (useCache) {
            // remove cached modules from request array
            for (const module of [...modules]) {
                let moduleEntries = getModule(code, module);
                if (moduleEntries) {
                    modules.splice(modules.indexOf(module), 1);
                    languageEntries = {...languageEntries, [module]: moduleEntries};
                }
            }
        }

        if (modules.length > 0) {
            let data = await api.apiCall("language/getEntries", { code: code, modules: modules });

            if (useCache) {
                if (data && data.success) {
                    // insert into cache
                    loadModules(code, data.entries);
                    data.entries = {...data.entries, ...languageEntries};
                    data.cached = false;
                }
            }

            return data;
        } else {
            return { success: true, msg: "", entries: languageEntries, code: code, cached: true };
        }
    }, [currentLocale, getModule, loadModules]);

    const ctx = {
        currentLocale: currentLocale,
        translate: translate,
        requestModules: requestModules,
        setLanguageByCode: setLanguageByCode,
    };

    return (
        <LocaleContext.Provider value={ctx}>
            {props.children}
        </LocaleContext.Provider>
    );
}

export {LocaleContext, LocaleProvider};