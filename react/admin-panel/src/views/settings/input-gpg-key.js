import {Box, IconButton, styled, TextField} from "@mui/material";
import {Delete, Upload} from "@mui/icons-material";
import React, {useCallback, useContext, useRef, useState} from "react";
import {LocaleContext} from "shared/locale";
import VisuallyHiddenInput from "../../elements/hidden-file-upload";

const StyledGpgKeyInput = styled(Box)((props) => ({
    display: "grid",
    gridTemplateColumns: "40px auto",
    "& button": {
        padding: 0,
        borderWidth: 1,
        borderStyle: "solid",
        borderColor: props.theme.palette.grey[400],
        borderTopLeftRadius: 5,
        borderBottomLeftRadius: 5,
        borderTopRightRadius: 0,
        borderBottomRightRadius: 0,
        backgroundColor: props.theme.palette.grey[300],
    },
    "& > div > div": {
        borderTopLeftRadius: 0,
        borderBottomLeftRadius: 0,
    }
}));

export default function GpgKeyInput(props) {

    const { value, api, showDialog, onChange, ...other } = props;
    const {translate: L} = useContext(LocaleContext);
    const isConfigured = !!value;
    const fileInputRef = useRef(null);

    const onRemoveKey = useCallback(() => {
        api.settingsRemoveGPG().then(data => {
            if (!data.success) {
                showDialog(data.msg, L("settings.remove_gpg_key_error"));
            } else {
                onChange(null);
            }
        });
    }, [api, showDialog, onChange]);

    const onImportGPG = useCallback((publicKey) => {
        api.settingsImportGPG(publicKey).then(data => {
            if (!data.success) {
                showDialog(data.msg, L("settings.import_gpg_key_error"));
            } else {
                onChange(data.gpgKey);
            }
        });
    }, [api, showDialog, onChange]);

    const onOpenDialog = useCallback(() => {
        if (isConfigured) {
            showDialog(
                L("settings.remove_gpg_key_text"),
                L("settings.remove_gpg_key"),
                [L("general.cancel"), L("general.remove")],
                button => button === 1 ? onRemoveKey() : true
            );
        } else if (fileInputRef?.current) {
            fileInputRef.current.click();
        }
    }, [showDialog, isConfigured, onRemoveKey, fileInputRef?.current]);

    const getFileContents = useCallback((file, callback) => {
        let reader = new FileReader();
        let data = "";
        reader.onload = function(event) {
            data += event.target.result;
            if (reader.readyState === 2) {
                if (!data.match(/^-+\s*BEGIN/m)) {
                    showDialog(L("account.invalid_gpg_key"), L("account.error_reading_file"));
                    return false;
                } else {
                    callback(data);
                }
            }
        };
        reader.readAsText(file);
    }, [showDialog]);

    return <StyledGpgKeyInput {...other}>
        <IconButton onClick={onOpenDialog}>
            { isConfigured ? <Delete color={"error"} /> : <Upload color={"success"} /> }
        </IconButton>
        <VisuallyHiddenInput ref={fileInputRef} type={"file"} onChange={e => {
            let file = e.target.files[0];
            getFileContents(file, (data) => {
                onImportGPG(data);
            });
            return false;
        }} />
        <TextField variant={"outlined"} size={"small"} disabled={true}
            value={value?.fingerprint ?? L("settings.no_gpg_key_configured")} />
    </StyledGpgKeyInput>
}