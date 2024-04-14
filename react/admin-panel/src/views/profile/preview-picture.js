import {Box, Slider, styled} from "@mui/material";
import {useContext, useState} from "react";
import {LocaleContext} from "shared/locale";

const PreviewProfilePictureBox = styled(Box)((props) => ({
    position: "relative",
}));

const PictureBox = styled(Box)((props) => ({
    backgroundRepeat: "no-repeat",
    backgroundSize: "contain"
}));

const SelectionBox = styled(Box)((props) => ({
    position: "absolute",
    border: "1px solid black",
    borderRadius: "50%",
}));

export default function PreviewProfilePicture(props) {

    const {translate: L} = useContext(LocaleContext);
    const {img, scale, setScale, ...other} = props;

    let size = "auto";
    let displaySize = ["auto", "auto"];
    let offsetY = 0;
    let offsetX = 0;

    if (img) {
        displaySize[0] = Math.min(img.naturalWidth, 400);
        displaySize[1] = img.naturalHeight * (displaySize[0] / img.naturalWidth);
        size = Math.min(...displaySize) * (scale / 100.0);
        offsetX = displaySize[0] / 2 - size / 2;
        offsetY = displaySize[1] / 2 - size / 2;
    }

    return <PreviewProfilePictureBox {...other} textAlign={"center"}>
        <PictureBox width={displaySize[0]} height={displaySize[1]}
            sx={{backgroundImage: `url("${img.src}")`, width: displaySize[0], height: displaySize[1], filter: "blur(5px)"}}
            title={L("account.profile_picture_preview")} />
        <PictureBox width={displaySize[0]} height={displaySize[1]}
                    position={"absolute"} top={0} left={0}
                    sx={{backgroundImage: `url("${img.src}")`, width: displaySize[0], height: displaySize[1],
                    clipPath: `circle(${scale*0.50}%)`}}
                    title={L("account.profile_picture_preview")} />
        <SelectionBox width={size} height={size} top={offsetY} left={offsetX} />
        <Box mt={1}>
            <label>{L("account.profile_picture_scale")}: {scale}%</label>
            <Slider value={scale} min={50} max={100} onChange={e => setScale(e.target.value)} />
        </Box>
    </PreviewProfilePictureBox>
}