import {useNavigate} from "react-router-dom";

export default function useEditorNavigate(L, showDialog) {

    const navigate = useNavigate();

    return (uri, modified, options = null) => {

        if (!modified) {
            navigate(uri, options ?? {});
        } else {
            showDialog(
                "You still have unsaved changes, are you really sure you want to leave this view?",
                "Unsaved changes",
                [L("general.cancel"), L("general.leave")],
                (buttonIndex) => buttonIndex === 1 && navigate(uri, options ?? {})
            )
        }
    };

}