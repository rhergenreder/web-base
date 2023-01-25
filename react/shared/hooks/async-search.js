import {useEffect, useState} from "react";


export default function useAsyncSearch(callback, minLength = 1) {

    const [searchString, setSearchString] = useState("");
    const [results, setResults] = useState(null);

    useEffect(() => {
        console.log("searchString:", searchString);
        if (minLength > 0 && (!searchString || searchString.length < minLength)) {
            setResults([]);
            return;
        }

        callback(searchString).then(results => {
            setResults(results || null);
        });
    }, [searchString]);

    return [searchString, setSearchString, results];
}