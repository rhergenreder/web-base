import ProfilePicture from "shared/elements/profile-picture";
import {Box} from "@mui/material";

export default function ProfileLink(props) {
    const {size, user, text, sx, ...other} = props;

    let newSx = sx ? {...sx} : {};
    if (!newSx.hasOwnProperty("gridGap")) {
        newSx.gridGap = 8;
    }

    if (props.onClick && !newSx.hasOwnProperty("cursor")) {
        newSx.cursor = "pointer";
    }

    return <Box display={"grid"} sx={newSx} gridTemplateColumns={size + "px auto"} alignItems={"center"} {...other}>
        <ProfilePicture user={user} size={size} />
        {typeof text === "string" ? text : (user.fullName || user.name)}
    </Box>
}