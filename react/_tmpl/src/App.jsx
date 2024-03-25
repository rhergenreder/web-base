import {useMemo} from "react";
import API from "shared/api";

export default function App() {

    const api = useMemo(() => new API(), []);

    return <></>
}