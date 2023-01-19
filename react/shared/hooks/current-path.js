import {useLocation, useParams} from "react-router-dom";


export default function useCurrentPath() {
    const location = useLocation();
    const params = useParams();

    const { pathname } = location;

    if (!Object.keys(params).length) {
        return pathname; // we don't need to replace anything
    }

    let path = pathname;
    Object.entries(params).forEach(([paramName, paramValue]) => {
        if (paramValue) {
            path = path.replace(paramValue, `:${paramName}`);
        }
    });
    return path;
}