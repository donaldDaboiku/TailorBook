<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Expense;
use Illuminate\Support\Str;

class ShopRecords
{
    public function csv(Business $business): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'record',
            'customer',
            'phone',
            'whatsapp',
            'email',
            'gender',
            'address',
            'notes',
            'archived',
            'date',
            'template',
            'field',
            'value',
            'unit',
            'job',
            'amount',
            'method',
            'reference',
            'detail',
        ]);

        $unit = $business->measurement_unit?->value ?? 'in';

        fputcsv($handle, $this->row([
            'record' => 'shop',
            'customer' => $business->name,
            'phone' => $business->phone,
            'whatsapp' => $business->whatsapp_phone,
            'detail' => $business->user?->email,
        ]));

        $customers = Customer::withTrashed()
            ->where('business_id', $business->id)
            ->with([
                'measurements' => fn ($query) => $query->withTrashed()->with(['template', 'values.field']),
                'jobs' => fn ($query) => $query->withTrashed(),
                'payments' => fn ($query) => $query->withTrashed()->with(['job' => fn ($job) => $job->withTrashed()]),
            ])
            ->orderBy('name')
            ->get();

        foreach ($customers as $customer) {
            fputcsv($handle, $this->customerRow($customer, 'client'));

            foreach ($customer->measurements as $measurement) {
                foreach ($measurement->values as $value) {
                    fputcsv($handle, $this->row([
                        'record' => 'measurement',
                        'customer' => $customer->name,
                        'phone' => $customer->phone,
                        'archived' => $measurement->trashed() ? 'yes' : 'no',
                        'date' => $measurement->taken_on?->toDateString(),
                        'template' => $measurement->template?->name,
                        'field' => $value->field?->label,
                        'value' => Units::fromCm((string) $value->value_cm, $unit),
                        'unit' => $unit,
                    ]));
                }
            }

            foreach ($customer->jobs as $job) {
                fputcsv($handle, $this->row([
                    'record' => 'job',
                    'customer' => $customer->name,
                    'phone' => $customer->phone,
                    'archived' => $job->trashed() ? 'yes' : 'no',
                    'date' => $job->service_date?->toDateString(),
                    'job' => $job->title,
                    'amount' => (string) $job->agreed_amount,
                ]));
            }

            foreach ($customer->payments as $payment) {
                fputcsv($handle, $this->row([
                    'record' => 'payment',
                    'customer' => $customer->name,
                    'phone' => $customer->phone,
                    'archived' => $payment->trashed() ? 'yes' : 'no',
                    'date' => $payment->paid_on?->toDateString(),
                    'job' => $payment->job?->title,
                    'amount' => (string) $payment->amount,
                    'method' => $payment->method?->value,
                    'reference' => $payment->reference,
                    'detail' => $payment->note,
                ]));
            }
        }

        $expenses = Expense::withTrashed()
            ->where('business_id', $business->id)
            ->with('category')
            ->orderBy('spent_on')
            ->get();

        foreach ($expenses as $expense) {
            fputcsv($handle, $this->row([
                'record' => 'expense',
                'archived' => $expense->trashed() ? 'yes' : 'no',
                'date' => $expense->spent_on?->toDateString(),
                'field' => $expense->category?->name,
                'amount' => (string) $expense->amount,
                'method' => $expense->method?->value,
                'detail' => $expense->description ?: $expense->note,
            ]));
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    public function filename(Business $business): string
    {
        $slug = Str::slug($business->name) ?: 'shop';

        return $slug.'-records-'.now()->format('Y-m-d').'.csv';
    }

    /**
     * @param  array<string, string|null>  $values
     * @return list<string>
     */
    private function customerRow(Customer $customer, string $record): array
    {
        return $this->row([
            'record' => $record,
            'customer' => $customer->name,
            'phone' => $customer->phone,
            'whatsapp' => $customer->whatsapp_phone,
            'email' => $customer->email,
            'gender' => $customer->gender?->value,
            'address' => $customer->address,
            'notes' => $customer->notes,
            'archived' => $customer->trashed() ? 'yes' : 'no',
        ]);
    }

    /**
     * @param  array<string, string|null>  $values
     * @return list<string>
     */
    private function row(array $values): array
    {
        $keys = [
            'record', 'customer', 'phone', 'whatsapp', 'email', 'gender', 'address', 'notes',
            'archived', 'date', 'template', 'field', 'value', 'unit', 'job', 'amount',
            'method', 'reference', 'detail',
        ];

        return array_map(
            fn (string $key) => $this->cell($values[$key] ?? ''),
            $keys,
        );
    }

    private function cell(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
