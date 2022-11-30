import React, {useState} from 'react';
import {L} from "shared/locale";
import {Box} from "@material-ui/core";
import {makeStyles} from "@material-ui/core/styles";

const useStyles = makeStyles((theme) => ({
    languageFlag: {
        margin: theme.spacing(0.2),
        cursor: "pointer",
        border: 0,
    }
}));

export default function LanguageSelection(props) {

    const api = props.api;
    const classes = useStyles();
    let [languages, setLanguages] = useState(null);

    const onSetLanguage = (code) => {
        api.setLanguageByCode(code).then((res) => {
            if (res.success) {
                props.onUpdateLocale();
            } else {
                alert(res.msg);
            }
        });
    };

    let flags = [];
    if (languages === null) {
        api.getLanguages().then((res) => {
            setLanguages(res.languages);
        });
    } else {
        for (const language of Object.values(languages)) {
            let key = `lang-${language.code}`;
            flags.push(<button type={"button"} title={language.name} onClick={() => onSetLanguage(language.code)}
                               key={key} className={classes.languageFlag} >
                <img alt={key} src={`/img/icons/lang/${language.code}.gif`} />
            </button>);
        }
    }

    return <Box mt={1}>
        {L("general.language") + ": "} { flags }
    </Box>
}