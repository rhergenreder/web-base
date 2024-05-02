import {Box, Button, CircularProgress, Slider, styled} from "@mui/material";
import {useCallback, useContext, useRef, useState} from "react";
import {LocaleContext} from "shared/locale";
import PreviewProfilePicture from "./preview-picture";
import {Delete, Edit} from "@mui/icons-material";
import ProfilePicture from "shared/elements/profile-picture";

const ProfilePictureBox = styled(Box)((props) => ({
    padding: props.theme.spacing(2),
    display: "grid",
    gridTemplateRows: "auto 60px",
    gridGap: props.theme.spacing(2),
    textAlign: "center",
}));

const VerticalButtonBar = styled(Box)((props) => ({
    "& > button": {
        width: "100%",
        marginBottom: props.theme.spacing(1),
    }
}));

export default function EditProfilePicture(props) {

    // meta
    const {translate: L} = useContext(LocaleContext);
    // const [scale, setScale] = useState(100);
    const scale = useRef(100);
    const {api, showDialog, setProfile, profile, setDialogData, ...other} = props

    const onUploadPicture = useCallback((data) => {
        api.uploadPicture(data, scale.current / 100.0).then((res) => {
            if (!res.success) {
                showDialog(res.msg, L("Error uploading profile picture"));
            } else {
                setProfile({...profile, profilePicture: res.profilePicture});
            }
        })
    }, [api, scale.current, showDialog, profile]);

    const onRemoveImage = useCallback(() => {
        api.removePicture().then((res) => {
            if (!res.success) {
                showDialog(res.msg, L("Error removing profile picture"));
            } else {
                setProfile({...profile, profilePicture: null});
            }
        });
    }, [api, showDialog, profile]);

    const onOpenDialog = useCallback((file = null, data = null) => {

        let img = null;
        if (data !== null) {
            img = new Image();
            img.src = data;
        }

        setDialogData({
            show: true,
            title: L("account.change_picture_title"),
            text: L("account.change_picture_text"),
            options: data === null ? [L("general.cancel")] : [L("general.apply"), L("general.cancel")],
            inputs: data === null ? [{
                key: "pfp-loading",
                type: "custom",
                element: CircularProgress,
            }] : [
                {
                    key: "pfp-preview",
                    type: "custom",
                    element: PreviewProfilePicture,
                    img: img,
                    scale: scale.current,
                    setScale: (v) => scale.current = v,
                },
            ],
            onOption: (option) => {
                if (option === 1 && file) {
                    onUploadPicture(file)
                }

                // scale.current = 100;
            }
        })
    }, [setDialogData, onUploadPicture]);

    const onSelectImage = useCallback(() => {
        let fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.accept = "image/jpeg,image/jpg,image/png";
        fileInput.onchange = () => {
            let file = fileInput.files[0];
            if (file) {
                let reader = new FileReader();
                reader.onload = function (e) {
                    onOpenDialog(file, e.target.result);
                }

                onOpenDialog();
                reader.readAsDataURL(file);
            }
        };
        fileInput.click();
    }, [onOpenDialog]);


    return <ProfilePictureBox {...other}>
        <ProfilePicture user={profile} onClick={onSelectImage} />
        <VerticalButtonBar>
            <Button variant="outlined" size="small"
                    startIcon={<Edit />}
                    onClick={onSelectImage}>
                {L("account.change_picture")}
            </Button>
            {profile.profilePicture &&
                <Button variant="outlined" size="small"
                    startIcon={<Delete />} color={"error"}
                    onClick={() => setDialogData({
                        show: true,
                        title: L("account.picture_remove_title"),
                        message: L("account.picture_remove_text"),
                        options: [L("general.confirm"), L("general.cancel")],
                        onOption: (option) => option === 1 ? onRemoveImage() : true
                    })}>
                    {L("account.remove_picture")}
                </Button>
            }
        </VerticalButtonBar>
    </ProfilePictureBox>
}