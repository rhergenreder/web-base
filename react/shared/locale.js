import React, {useReducer} from 'react';
import {createContext, useCallback, useState} from "react";
import { enUS as dateFnsEN, de as dateFnsDE } from 'date-fns/locale';
import {getCookie, getParameter} from "./util";

const LocaleContext = createContext(null);

function reducer(entries, action) {
    let _entries = entries;

    switch (action.type) {
        case 'loadModule':
            if (!_entries.hasOwnProperty(action.code)) {
                _entries[action.code] = {};
            }
            if (_entries[action.code].hasOwnProperty(action.module)) {
                _entries[action.code][action.module] = {..._entries[action.code][action.module], ...action.newEntries};
            } else {
                _entries[action.code][action.module] = action.newEntries;
            }
            break;
        case 'loadModules':
            _entries = {...entries, [action.code]: { ...entries[action.code], ...action.modules }};
            break;
        default:
            throw new Error();
    }

    return _entries;
}

function LocaleProvider(props) {

    const [entries, dispatch] = useReducer(reducer, window.languageEntries || {});
    const [currentLocale, setCurrentLocale] = useState(window.languageCode || getParameter("lang") || getCookie("lang") || "en_US");

    const translate = useCallback((key, defaultTranslation = null) => {

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

        return key ? defaultTranslation || "[" + key + "]" : "";
    }, [currentLocale, entries]);

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
            if (code === null && api.language) {
                code = api.language.code;
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
                    dispatch({type: "loadModules", code: code, modules: data.entries});
                    data.entries = {...data.entries, ...languageEntries};
                    data.cached = false;
                }
            }

            return data;
        } else {
            return { success: true, msg: "", entries: languageEntries, code: code, cached: true };
        }
    }, [currentLocale, getModule, dispatch]);

    const toDateFns = useCallback(() => {
        switch (currentLocale) {
            case 'de_DE':
                return dateFnsDE;
            case 'en_US':
            default:
                return dateFnsEN;
        }
    }, [currentLocale]);

    const ctx = {
        currentLocale: currentLocale,
        translate: translate,
        requestModules: requestModules,
        setLanguageByCode: setLanguageByCode,
        toDateFns: toDateFns,
        setCurrentLocale: setCurrentLocale,
    };

    return (
        <LocaleContext.Provider value={ctx}>
            {props.children}
        </LocaleContext.Provider>
    );
}

export {LocaleContext, LocaleProvider};