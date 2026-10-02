@isset($input_fields)
    @foreach ($input_fields ?? [] as $item)
        @if ($item->type == "text" || $item->type == "file")
            <div class="col-xl-12 form-group mb-20">
                <label class="mb-3">{{ __($item->label) }}
                    @if ($item->required == true)
                        <span class="text-danger">*</span>
                    @endif
                </label>
                <input type="{{ $item->type }}" name="{{ $item->name }}" class="form-control" value="{{ old($item->name) }}" placeholder="Type Here">
            </div>
        @elseif ($item->type == "textarea")
            @include('admin.components.form.textarea',[
                'label'     => __($item->label),
                'name'      => $item->name,
                'attribute' => ($item->required == true) ? "required=true" : "",
                'class'     => "form-control mb-3",
                'value'     => old($item->name),
            ])
        @endif
    @endforeach
@endisset
