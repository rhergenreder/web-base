import React from "react";
import { enUS as dateFnsEN } from "date-fns/locale/index.js";

export default class LocaleEnglish {
    constructor() {
        this.code = "en_US";
        this.name = "American English";
        this.entries = {};
    }

    toDateFns() {
        return dateFnsEN;
    }
};