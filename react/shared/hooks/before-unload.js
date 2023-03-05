import {useCallback, useEffect} from "react";

export default function useBeforeUnload(modified) {

    const capture = useCallback((event) => {
        if (modified) {
            event.preventDefault();
        }
    }, [modified]);

    useEffect(() => {
        window.addEventListener("beforeunload", capture, {capture: true});
        return () => window.removeEventListener("beforeunload", capture, { capture: true });
    }, []);

}