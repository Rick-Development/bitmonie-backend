<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'first_name'          => $this->firstname ?? '',
            'last_name'           => $this->lastname ?? '',
            'username'            => $this->username ?? '',
            'mobile_code'         => $this->mobile_code ?? '',
            'mobile'              => $this->mobile ?? '',
            'full_mobile'         => $this->full_mobile ?? '',
            'account_no'          => $this->account_no ?? '',
            'gender'              => $this->gender ?? '',
            'birthdate'           => $this->birthdate ?? '',
            'address'             => [
                'country'         => $this->address->country ?? '',
                'city'            => $this->address->city ?? '',
                'state'           => $this->address->state ?? '',
                'zip'             => $this->address->zip ?? '',
                'address'         => $this->address->address ?? '',
            ],
            'status'              => $this->status ?? '',
            'pin_status'          => $this->pin_status,
            'email'               => $this->email ?? '',
            'image'               => $this->image ? $this->image : '',
            'ver_code'            => $this->ver_code ?? '',
            'ver_code_send_at'    => $this->ver_code_send_at ?? '',
            'email_verified_at'   => $this->email_verified_at ?? '',
            'email_verified'      => $this->email_verified ?? '',
            'sms_verified'        => $this->sms_verified ?? 0,
            'kyc_verified'        => $this->kyc_verified ?? 0,
            'two_factor_verified' => $this->two_factor_verified ?? 0,
            'two_factor_status'   => $this->two_factor_status ?? 0,
            'kyc'                 => [
                'data'            => $this->kyc->data ?? [] ,
                'reject_reason'   => $this->kyc->reject_reason ?? "",
            ]
        ];
    }
}
