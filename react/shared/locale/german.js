import React from "react";
import { de as dateFnsDE } from "date-fns/locale/index.js";

export default class LocaleGerman {
    constructor() {
        this.code = "de_DE";
        this.name = "Deutsch Standard";
        this.entries = {};
    }

    toDateFns() {
        return dateFnsDE;
    }
}