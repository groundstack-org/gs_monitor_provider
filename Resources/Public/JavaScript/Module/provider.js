function jumpToUrl(URL) {
    window.location.href = URL;
    return false;
};

function jumpExt(URL, anchor) {
    var anc = anchor ? anchor : "";
    window.location.href = URL + (T3_THIS_LOCATION ? "&returnUrl=" + T3_THIS_LOCATION : "") + anc;
    return false;
}
