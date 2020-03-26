<?php

namespace App\Imports;

use App\{User, FailedEntriesUser};
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;  
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;     

class UsersImport implements OnEachRow, WithValidation, WithHeadingRow, SkipsOnFailure, WithBatchInserts, SkipsOnError
{
    use Importable, SkipsFailures, SkipsErrors;

    public function __construct($company_id)
    {
        $this->company_id = $company_id;
    }

    public function onRow(Row $row)
    {
        $row = $row->toArray();

        $row['username'] = $this->getUniqueUserName($row['firstname'], $row['lastname']);

        $user = User::Create($row);

        $user->dna_groups()->sync([$this->company_id]);
    }

    /**
     * @param Failure[] $failures
     */
    public function onFailure(Failure ...$failures)
    {
        // Handle the failures how you'd like.
        foreach ($failures as $failure) {
            $values = $failure->values();
            $row['row_id'] = $failure->row(); // row that went wrong
            $row['attribute'] = $failure->attribute(); // row that went wrong
            $row['error_msg'] = $failure->errors()[0]; // Actual error messages from Laravel validator
            $row['firstname'] = $values['firstname'] ? $values['firstname'] : null;
            $row['lastname'] = $values['lastname'] ? $values['lastname'] : null;
            $row['sex'] = $values['sex'] ? $values['sex'] : null;
            $row['email'] = $values['email'] ? $values['email'] : null;
            $row['country'] = $values['country'] ? $values['country'] : null;

            FailedEntriesUser::firstOrCreate($row);
        }
        $this->failures = array_merge($this->failures, $failures);
    }

    public function failures()
    {
        return $this->failures;
    }

    /**
    * @return array
    */
    public function rules(): array
    {
        return [
            'firstname'        => 'required|string|max:255',
            'lastname'         => 'required|string|max:255',
            'sex'              => ['required','string','regex:/^[MF]{1}$/'],
            'country'          => 'required|exists:countries,code',
            'email'            => 'required|string|email|max:255|unique:users'
        ];
    }

    /**
    * @return string unique username for users table
    * create username using first and last name
    */
    public function getUniqueUserName($firstname, $lastname) {
        $username = $firstname.$lastname.rand(0,1000);

        if (!User::where('username', $username)->first()) {
            return $username;
        } else {
            getUniqueUserName($firstname, $lastname);
        }
    }

    /**
     * @return int - batch size
     * This sets the batch size for number of records to be inserted in 
     * DB at a time.
     * Play around with this number to find the sweet spot.
     */
    public function batchSize(): int
    {
        return 1000;
    }
    
}
