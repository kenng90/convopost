@if ($variables != null)
    @foreach ($variables as $key => $itemBox)
        <h2>{{ __(ucfirst($key)) }}</h2>
        @if ($key == 'header' || $key == 'body')
            @foreach ($itemBox as $item)
                @php
                    $valueKey = $paramvalues[$key][$item['id']] ?? ($item['exampleValue'] ?? '');
                    $matchKey = $parammatch[$key][$item['id']] ?? '-3';
                @endphp
                @include('partials.input', [
                    'onvuechange' => 'setPreviewValue',
                    'id' => 'paramvalues['.$key.']['.$item['id'].']',
                    'name' => __('Variable').' '.$item['id'],
                    'placeholder' => $item['exampleValue'] ?? 'order.id',
                    'value' => $valueKey,
                    'required' => false,
                    'additionalInfos' => __('If using API defined value, enter the data path (e.g. order.id or customer_name).'),
                ])
                @include('partials.select', [
                    'id' => 'parammatch['.$key.']['.$item['id'].']',
                    'name' => __('Match with a contact field'),
                    'data' => $contactFields,
                    'required' => true,
                    'value' => $matchKey,
                    'additionalInfos' => __('Use API defined value for payload fields, or map to a contact field / static value.'),
                ])
            @endforeach
        @elseif ($key == 'document')
            @include('partials.input', ['id' => 'pdf', 'name' => __('PDF Document'), 'placeholder' => '', 'type' => 'file', 'required' => ! $campaign, 'accept' => 'application/pdf'])
        @elseif ($key == 'image')
            @include('partials.input', ['id' => 'imageupload', 'changevue' => 'handleImageUpload', 'name' => __('Select image'), 'placeholder' => '', 'type' => 'file', 'required' => ! $campaign, 'accept' => '.jpg, .jpeg, .png'])
        @elseif ($key == 'video')
            @include('partials.input', ['id' => 'imageupload', 'changevue' => 'handleVideoUpload', 'name' => __('Select video'), 'placeholder' => '', 'type' => 'file', 'required' => ! $campaign, 'accept' => '.mp4'])
        @elseif ($key == 'buttons')
            @foreach ($itemBox as $button)
                @foreach ($button as $keybtn => $item)
                    @php
                        $valueKey = $paramvalues[$key][$keybtn][$item['id']] ?? '';
                        $matchKey = $parammatch[$key][$keybtn][$item['id']] ?? '-3';
                    @endphp
                    @if ($item['type'] == 'URL')
                        @include('partials.input', [
                            'prepend' => $item['exampleValue'],
                            'id' => 'paramvalues['.$key.']['.$keybtn.']['.$item['id'].']',
                            'name' => $item['text'],
                            'placeholder' => '',
                            'value' => $valueKey,
                            'required' => false,
                        ])
                    @elseif ($item['type'] == 'COPY_CODE')
                        @include('partials.input', [
                            'id' => 'paramvalues['.$key.']['.$keybtn.']['.$item['id'].']',
                            'name' => $item['text'],
                            'placeholder' => $item['exampleValue'],
                            'value' => $valueKey,
                            'required' => false,
                        ])
                    @endif
                    @include('partials.select', [
                        'id' => 'parammatch['.$key.']['.$keybtn.']['.$item['id'].']',
                        'name' => __('Match with a contact field'),
                        'data' => $contactFields,
                        'required' => true,
                        'value' => $matchKey,
                    ])
                @endforeach
            @endforeach
        @endif
        <hr />
    @endforeach
@endif
