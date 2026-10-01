{{-- check pin validations --}}
<div class="modal fade checkPin" id="checkPin" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="editModalLabel">
                <h5 class="modal-title">{{ __("Verify Your PIN") }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-12 form-group mb-0" id="show_hide_password">
                        <input type="password" required class="form--control pinCheck" name="pin" placeholder="{{ __("Pin") }}">
                        <a href="javascript:void(0)" class="show-pass"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                        <label class="exist_pi text-start"></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn--base w-100 finalConfirmed" disabled>{{ __("Confirm") }}</button>
            </div>
        </div>
    </div>
</div>
{{-- check pin validations --}}

@push('script')
<script>
    $(document).ready(function() {
        $(document).on('click', '#show_hide_password a', function(event) {
            event.preventDefault();
            if($('#show_hide_password input').attr("type") == "text"){
                $('#show_hide_password input').attr('type', 'password');
                $('#show_hide_password i').addClass( "fa-eye-slash" );
                $('#show_hide_password i').removeClass( "fa-eye" );
            }else if($('#show_hide_password input').attr("type") == "password"){
                $('#show_hide_password input').attr('type', 'text');
                $('#show_hide_password i').removeClass( "fa-eye-slash" );
                $('#show_hide_password i').addClass( "fa-eye" );
            }
        });
    });
</script>
@endpush
