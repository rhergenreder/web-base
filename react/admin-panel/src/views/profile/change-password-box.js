import {Password} from "@mui/icons-material";
import SpacedFormGroup from "../../elements/form-group";
import {Box, FormControl, FormLabel, TextField} from "@mui/material";
import PasswordStrength from "shared/elements/password-strength";
import CollapseBox from "./collapse-box";
import React, {useContext} from "react";
import {LocaleContext} from "shared/locale";

export default function ChangePasswordBox(props) {

    // meta
    const {changePassword, setChangePassword, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    return <CollapseBox title={L("account.change_password")}
                        icon={<Password />}
                        {...other} >
        <SpacedFormGroup>
            <FormLabel>{L("account.password_old")}</FormLabel>
            <FormControl>
                <TextField variant={"outlined"}
                           size="small"
                           type={"password"}
                           placeholder={L("general.unchanged")}
                           value={changePassword.old}
                           onChange={e => setChangePassword({...changePassword, old: e.target.value })} />
            </FormControl>
        </SpacedFormGroup>
        <SpacedFormGroup>
            <FormLabel>{L("account.password_new")}</FormLabel>
            <FormControl>
                <TextField variant={"outlined"}
                           size="small"
                           type={"password"}
                           value={changePassword.new}
                           onChange={e => setChangePassword({...changePassword, new: e.target.value })} />
            </FormControl>
        </SpacedFormGroup>
        <SpacedFormGroup>
            <FormLabel>{L("account.password_confirm")}</FormLabel>
            <FormControl>
                <TextField variant={"outlined"}
                           size="small"
                           type={"password"}
                           value={changePassword.confirm}
                           onChange={e => setChangePassword({...changePassword, confirm: e.target.value })} />
            </FormControl>
        </SpacedFormGroup>
        <Box className={"w-50"}>
            <PasswordStrength password={changePassword.new} minLength={6} />
        </Box>
    </CollapseBox>
}