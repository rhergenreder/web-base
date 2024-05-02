import {Box, Breadcrumbs, Grid, styled} from "@mui/material";

const StyledViewContent = styled(Box)((props) => ({
    padding: props.theme.spacing(2),
}))

const StyledNavigation = styled(Grid)((props) => ({
    alignSelf: "end",
    "& ol": {
        justifyContent: "end",
        margin: "auto"
    }
}));

export default function ViewContent(props) {

    const {title, path, children, ...other} = props;

    return <StyledViewContent {...other}>
        <Grid container>
            <Grid item xs={6}>
                <h2>{title}</h2>
            </Grid>
            <StyledNavigation item xs={6}>
                <Breadcrumbs>{path}</Breadcrumbs>
            </StyledNavigation>
        </Grid>
        {children}
    </StyledViewContent>

}