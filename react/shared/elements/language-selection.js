import React, {useCallback, useContext, useState} from 'react';
import {Box, Button} from "@mui/material";
import {LocaleContext} from "shared/locale";

/*
const useStyles = makeStyles((theme) => ({
    languageFlag: {
        margin: theme.spacing(0.2),
        cursor: "pointer",
        border: 0,
    }
}));
*/

export default function LanguageSelection(props) {

    const api = props.api;
    const [languages, setLanguages] = useState(null);
    const {translate: L, setLanguageByCode} = useContext(LocaleContext);

    const onSetLanguage = useCallback((code) => {
        setLanguageByCode(api, code).then((res) => {
            if (!res.success) {
                alert(res.msg);
            }
        });
    }, []);

    let flags = [];
    if (languages === null) {
        api.getLanguages().then((res) => {
            if (res.success) {
                setLanguages(res.languages);
            } else {
                setLanguages({});
                alert(res.msg);
            }
        });
    } else {
        for (const language of Object.values(languages)) {
            let key = `lang-${language.code}`;
            flags.push(<Button type={"button"} title={language.name} onClick={() => onSetLanguage(language.code)}
                               key={key} >
                <img alt={key} src={`/img/icons/lang/${language.code}.gif`} />
            </Button>);
        }
    }

    return <Box mt={1}>
        {L("general.language") + ": "} { flags }
    </Box>
}