@extends('mail.layouts.base')

@section('mail_title', $subjectLine ?? 'Broadcast')

@section('mail_card')
    <tr>
        <td style="padding:28px 32px 8px;">
            {!! $htmlBody !!}
        </td>
    </tr>
    @include('mail.partials.card-footer-by-app')
@endsection
