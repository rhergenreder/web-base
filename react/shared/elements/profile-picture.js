import {Box, styled} from "@mui/material";
import {useContext} from "react";
import {LocaleContext} from "../locale";

const PictureBox = styled("img")({
    width: "100%",
    clipPath: "circle(50%)",
});

const PicturePlaceholderBox = styled(Box)((props) => ({
    display: "flex",
    justifyContent: "center",
    alignItems: "center",
    background: "radial-gradient(circle closest-side, gray 98%, transparent 100%);",
    containerType: "inline-size",
    width: "100%",
    height: "100%",
    "& > span": {
        textAlign: "center",
        fontSize: "30cqw",
        color: "black",
    }
}));

export default function ProfilePicture(props) {

    const {user, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    const initials = (user.fullName || user.name)
        .split(" ")
        .map(n => n.charAt(0).toUpperCase())
        .join("");

    const isClickable = !!other.onClick;
    const sx = isClickable ? {cursor: "pointer"} : {};

    if (user.profilePicture) {
        return <PictureBox src={`/img/uploads/user/${user.id}/${user.profilePicture}`} sx={sx}
                    alt={L("account.profile_picture_of") + " " + (user.fullName || user.name)}
                    {...other} />;
    } else {
        return <PicturePlaceholderBox sx={sx} {...other}>
            <span>{initials}</span>
        </PicturePlaceholderBox>;
    }
}