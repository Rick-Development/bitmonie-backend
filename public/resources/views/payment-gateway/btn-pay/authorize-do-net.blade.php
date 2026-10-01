<!DOCTYPE html>
<html>

<!--
    This file is a standalone HTML page demonstrating usage of the Authorize.net
    Accept JavaScript library using the integrated payment information form.

    For complete documentation for the Accept JavaScript library, see
    https://developer.authorize.net/api/reference/features/acceptjs.html
-->

<head>
    <title>Sample form</title>
    <style>
        /* Hide the button */
        #payButton {
            display: none;
        }
        .close {
            display: none !important;
        }
    </style>
</head>

<body>

<script type="text/javascript"
    src="https://jstest.authorize.net/v3/AcceptUI.js"
    charset="utf-8">
</script>

<form id="paymentForm"
    method="POST" action="{{ route('user.add.money.authorize.payment.submit') }}">
    @csrf
    <input type="hidden" name="dataValue" id="dataValue" />
    <input type="hidden" name="dataDescriptor" id="dataDescriptor" />
    <input type="hidden" name="token" value="{{ request()->token }}" />
    <button type="button"
        id="payButton"
        class="AcceptUI close"
        data-billingAddressOptions='{"show":true, "required":false}'
        data-apiLoginID="{{ $output['login_id'] }}"
        data-clientKey="{{ $output['public_key'] }}"
        data-acceptUIFormBtnTxt="Submit"
        data-acceptUIFormHeaderTxt="Card Information"
        data-responseHandler="responseHandler">Pay
    </button>
</form>

<script type="text/javascript">

window.addEventListener("load", () => {
    document.getElementById("payButton").click();
});

function responseHandler(response) {
    if (response.messages.resultCode === "Error") {
        var i = 0;
        while (i < response.messages.message.length) {
            console.log(
                response.messages.message[i].code + ": " +
                response.messages.message[i].text
            );
            i = i + 1;
        }
    } else {
        paymentFormUpdate(response.opaqueData);
    }
}

function paymentFormUpdate(opaqueData) {
    document.getElementById("dataDescriptor").value = opaqueData.dataDescriptor;
    document.getElementById("dataValue").value = opaqueData.dataValue;
    document.getElementById("paymentForm").submit();
}
</script>

</body>
</html>
