function jumpToUrl(URL) {
    window.location.href = URL;
    return false;
};

function jumpExt(URL, anchor) {
    var anc = anchor ? anchor : "";
    window.location.href = URL + (T3_THIS_LOCATION ? "&returnUrl=" + T3_THIS_LOCATION : "") + anc;
    return false;
}


(function() {
    // https://stackoverflow.com/questions/28970925/basic-javascript-password-generator
    function makeid() {
        var value = 20;
        var results = document.getElementById('results');
        var text = "";
        var shuffle = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!%@?=";
        var i = 0;
        for( i; i < value; i++ ) {
            text += shuffle.charAt(Math.floor(Math.random() * shuffle.length));
        }
        results.value = text;
    }

    function copyToClipboard() {
        /* Get the text field */
        var copyText = document.getElementById("results");

        /* Select the text field */
        copyText.select();
        copyText.setSelectionRange(0, 99999); /*For mobile devices*/

        /* Copy the text inside the text field */
        document.execCommand("copy");
    }

    document.addEventListener("DOMContentLoaded", function(event) {
        makeid();

        var copyBtn = document.querySelector("#copy-to-clipboard");
        copyBtn.addEventListener("click", copyToClipboard(document.querySelector("#results")));
    });
})();
