import {
    Box,
    Button,
    CircularProgress,
    Dialog, DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
    styled, TextField
} from "@mui/material";
import {useCallback, useContext, useState} from "react";
import {LocaleContext} from "shared/locale";
import {Delete, Edit, Upload} from "@mui/icons-material";
import ProfilePicture from "shared/elements/profile-picture";
import ReactCrop from 'react-image-crop'

import 'react-image-crop/dist/ReactCrop.css';

const ProfilePictureBox = styled(Box)((props) => ({
    padding: props.theme.spacing(1),
    display: "grid",
    gridTemplateRows: "auto calc(110px - " + props.theme.spacing(1) + ")",
    textAlign: "center",
    alignItems: "center",
    justifyItems: "center",
    "& img": {
        maxHeight: 150,
        width: "auto",
    }
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
    const {api, showDialog, setProfile, profile, setDialogData, ...other} = props

    // data
    const [crop, setCrop] = useState({ unit: 'px' });
    const [image, setImage] = useState({ loading: false, data: null, file: null });

    // ui
    const [isUploading, setUploading] = useState(false);

    const onCloseDialog = useCallback((event = null, reason = null) => {
        if (!reason || !["backdropClick", "escapeKeyDown"].includes(reason)) {
            setImage({loading: false, data: null, file: null});
        }
    }, []);

    const onUploadPicture = useCallback(() => {
        if (!isUploading) {
            setUploading(true);
            api.uploadPicture(image.file, crop.width, crop.x, crop.y).then(res => {
                setUploading(false);
                if (res.success) {
                    onCloseDialog();
                    setProfile({...profile, profilePicture: res.profilePicture});
                } else {
                    showDialog(res.msg, L("account.upload_profile_picture_error"));
                }
            })
        }
    }, [api, image, crop, isUploading, showDialog, profile, onCloseDialog]);

    const onRemoveImage = useCallback(() => {
        api.removePicture().then((res) => {
            if (!res.success) {
                showDialog(res.msg, L("account.remove_profile_picture_error"));
            } else {
                setProfile({...profile, profilePicture: null});
            }
        });
    }, [api, showDialog, profile]);

    const onSelectImage = useCallback(() => {
        let fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.accept = "image/jpeg,image/jpg,image/png";
        fileInput.onchange = () => {
            let file = fileInput.files[0];
            if (file) {
                let reader = new FileReader();
                reader.onload = function (e) {
                    const imageData = e.target.result;
                    const img = new Image();
                    img.src = imageData;
                    img.onload = () => {
                        let croppedSize;
                        if (img.width > img.height) {
                            croppedSize = Math.min(800, img.height);
                            setCrop({ x: (img.width - img.height) / 2, y: 0, unit: "px", width: croppedSize, height: croppedSize });
                        } else if (img.width < img.height) {
                            croppedSize = Math.min(800, img.width);
                            setCrop({ x: 0, y: (img.height - img.width) / 2, unit: "px", width: croppedSize, height: croppedSize });
                        } else {
                            croppedSize = Math.min(800, img.width);
                            setCrop({ x: 0, y: 0, unit: "px", width: croppedSize, height: croppedSize });
                        }

                        if (croppedSize < 150) {
                            setImage({ loading: false, file: null, data: null });
                            showDialog(L("account.profile_picture_invalid_dimensions"), L("general.error"));
                        } else {
                            setImage({ loading: false, file: file, data: imageData });
                        }
                    }
                }

                setImage({ file: null, data: null, loading: true });
                reader.readAsDataURL(file);
            }
        };
        fileInput.click();
    }, [showDialog]);

    return <>
        <ProfilePictureBox {...other}>
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
                            title: L("account.remove_picture"),
                            message: L("account.remove_picture_text"),
                            options: [L("general.cancel"), L("general.confirm")],
                            onOption: (option) => option === 1 ? onRemoveImage() : true
                        })}>
                        {L("account.remove_picture")}
                    </Button>
                }
            </VerticalButtonBar>
        </ProfilePictureBox>
        <Dialog open={image.loading || image.data !== null} maxWidth={"lg"}
            onClose={onCloseDialog}>
            <DialogTitle>
                {L("account.change_picture_title")}
            </DialogTitle>
            <DialogContent>
                <DialogContentText>
                    {L("account.change_picture_text")}
                </DialogContentText>
                {image.data ?
                    <ReactCrop onChange={c => setCrop(c)} crop={crop} keepSelection={true}
                               aspect={1} circularCrop={true} disabled={isUploading}
                               maxWidth={800} maxHeight={800} minWidth={150} minHeight={150}>
                        <img src={image?.data} alt={"preview"} />
                    </ReactCrop> :
                    <CircularProgress />
                }
            </DialogContent>
            <DialogActions>
                <Button variant={"outlined"} color={"error"} onClick={onCloseDialog}
                        disabled={isUploading}>
                    {L("general.cancel")}
                </Button>
                <Button variant={"outlined"} type={"submit"} onClick={onUploadPicture}
                        disabled={isUploading}
                        startIcon={isUploading ? <CircularProgress size={12} /> : <Upload />}>
                    {L(isUploading ? "general.uploading" : "general.submit")}
                </Button>
            </DialogActions>
        </Dialog>
    </>
}