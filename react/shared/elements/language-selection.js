import React, {useCallback, useContext, useState} from 'react';
import {Box, styled} from "@mui/material";
import {LocaleContext} from "shared/locale";

const LanguageFlag = styled(Box)((props) => ({
    display: "inline-block",
    marginRight: props.theme.spacing(0.5),
    cursor: "pointer"
}));

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
            flags.push(<LanguageFlag key={key}>
                    <img alt={key} src={`/img/icons/lang/${language.code}.gif`} onClick={() => onSetLanguage(language.code)} />
                </LanguageFlag>
            );
        }
    }

    return <Box mt={1}>
        {L("general.language") + ": "} { flags }
    </Box>
}