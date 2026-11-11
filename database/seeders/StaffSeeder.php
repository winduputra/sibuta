<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StaffSeeder extends Seeder
{
    /**
     * Division ID mapping for each staff group.
     *
     * Fixed by the requirements document:
     *   LPSE => 3, JF => 2, PBJ => 2, PSDM => 5
     * Division 1 (Kepala Biro) and 4 (Sub Bagian Umum) are intentionally unused.
     */
    private const DIVISION_BY_GROUP = [
        'LPSE' => 3,
        'JF' => 2,
        'PBJ' => 2,
        'PSDM' => 5,
    ];

    /**
     * Initial password for newly created ASN accounts is supplied through the
     * environment and hashed on write only. Existing accounts never have their
     * password overwritten by this seeder.
     */
    private const PASSWORD_ENV_KEY = 'SIBUTA_ASN_INITIAL_PASSWORD';

    /**
     * Seed ASN accounts from the official staff list.
     *
     * Idempotent: keyed on `nip`, so re-running never duplicates rows and never
     * resets the password of an account that already exists.
     */
    public function run(): void
    {
        $asnRoleId = Role::where('code', Role::ASN)->value('id');

        if ($asnRoleId === null) {
            throw new RuntimeException(
                'Role "' . Role::ASN . '" not found. Run RoleSeeder before StaffSeeder.'
            );
        }

        $staff = $this->staffList();

        $this->assertDivisionsExist($staff);
        $this->assertNoDuplicateNip($staff);
        $this->assertNoUsernameConflict($staff);

        $hashedPassword = Hash::make($this->initialPassword());
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($staff, $asnRoleId, $hashedPassword, &$created, &$updated) {
            foreach ($staff as $group => $members) {
                $divisionId = self::DIVISION_BY_GROUP[$group];

                foreach ($members as [$name, $nip]) {
                    $existing = User::where('nip', $nip)->first();

                    if ($existing !== null) {
                        // Keep the existing password: this seeder only refreshes
                        // name/role/division so the account stays usable.
                        $existing->update([
                            'name' => $name,
                            'username' => $nip,
                            'role_id' => $asnRoleId,
                            'division_id' => $divisionId,
                        ]);
                        $updated++;

                        continue;
                    }

                    User::create([
                        'name' => $name,
                        'username' => $nip,
                        'nip' => $nip,
                        'password' => $hashedPassword,
                        'role_id' => $asnRoleId,
                        'division_id' => $divisionId,
                    ]);
                    $created++;
                }
            }
        });

        $this->command->info("StaffSeeder: {$created} ASN created, {$updated} updated.");
    }

    /**
     * Resolve the initial ASN password from the environment.
     *
     * Required so no default credential can be shipped. The exception message
     * intentionally omits the value; the password is never logged.
     */
    private function initialPassword(): string
    {
        $password = env(self::PASSWORD_ENV_KEY);

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException(
                self::PASSWORD_ENV_KEY . ' is not set. Define it in the environment before running StaffSeeder.'
            );
        }

        return $password;
    }

    /**
     * Official staff list. Names and NIPs must be used verbatim.
     *
     * @return array<string, array<int, array{0: string, 1: string}>>
     */
    private function staffList(): array
    {
        return [
            'LPSE' => [
                ['DODI HENDRAWAN, ST., MEP', '197006291998031004'],
                ['Ir. FABIL HUDA', '196903261998031003'],
                ['MUHAMMAD YUSRON, ST., MT', '197701262006041010'],
                ['YAN SOPA, SE., MM', '198501202006041005'],
                ['SRI RANTI, S.Sos., MM', '197702111998032001'],
                ['LIA OKTIARA, SE., MM', '198010292008042001'],
                ['RANI ARSIKA SUBING, SE., MM', '198408122009022007'],
                ['ROSIDI, S.IP', '197012182000031003'],
                ['SARIFUDDIN, SE', '197307141998031004'],
                ['FIRDALIA', '197904072003122004'],
                ['ACHMAD ADENAN, S. M', '197107182007011005'],
                ['RIZALDO ABDULRACHMAN, S. Kom', '199907162025041002'],
                ['RIZKY YULIANTO, ST', '200007132025041001'],
                ['IRFANI MAHARANI, S. Kom', '199510192025042002'],
                ['NORI HERMAWAN, S. AP', '198502282010011006'],
                ['M. TAKIM', '197409292007011003'],
                ['RAHMA MARLIANA PUTRI, SE', '199103262025212013'],
                ['JUNAIDI', '198602272025211021'],
            ],
            'JF' => [
                ['Drs. IRHANNA, MM', '196710201987011003'],
                ['HERMALIA, SP., MM', '197501092000032004'],
                ['REFANSIUS MANGAMAN S. SE', '197008301995031001'],
                ['ADIKA RATU, S.Sos., MM', '197208221993032003'],
                ['BUDHI ANSORI, SH', '197609152002121006'],
                ['HERY WISNU HARYATNO, ST., MM', '198002282006041004'],
                ['NOVI HANDAYANI, ST., MT', '198011202006042011'],
                ['EKO AGUST PRIYONO, ST', '198008312003121002'],
                ['HARJANTO SETIAJI, ST., MM', '197408212002121003'],
                ['ANDRI HARDATAMA, SE., MM', '198101022010011013'],
                ['HERLI ANDRIANI, SH, MM', '197401182007012008'],
                ['HERNELI DIANAWATI, ST', '197304052005022005'],
                ['IHWAN NUDIN, S.Kep', '198204242009021003'],
                ['MAS MUHAMMAD ASRI M.S.Sos', '197112292008011007'],
                ['IVAN YULINDO, SE., MM', '197707192011011001'],
                ['LAZNAWATI, SE., MM', '197009031992032004'],
                ['AHMAD HERNAWAN, S.P', '197109172005011007'],
                ['SUBAROKAH SAFARI, SST', '197602212005011004'],
                ['HARISON YUSA, ST., MT', '198402082005011002'],
                ['AGUSTIADI, ST., MT', '197608022007011021'],
                ['ADE YURIZA, SH', '199007202015031003'],
            ],
            'PSDM' => [
                ['WAYAN PURWANAJATA, S.P', '197504271999021001'],
                ['YASIR HERYANTO, SE., MM', '197810292009021002'],
                ['SHERLI YESSI, ST., MT', '197711132003122002'],
                ['YULIANA USMAN, SH., MM', '198407272008042004'],
                ['RACHMAT, SE., MM', '197601232011011003'],
                ['FAULIANI ARIANI, A. Md', '197506112008012016'],
                ['ZAQI ILMAN JIWANDONO, S. Sos., M. Si', '199012242020121006'],
                ['INSAF SURAHMAN, SH', '197507082008011009'],
                ['ANDI ARIYANDI, S. Kom', '199803152025041001'],
                ['ARINI SUSANTO, S. Kom', '199304022025042001'],
                ['REZA SAPUTRAAZMI, S. Kom', '199309252025041004'],
                ['ERINDA ALIN KURNIA, S. Akt', '199811132025212012'],
                ['MUHAMMAD TUHRO', '198406032025211024'],
            ],
            'PBJ' => [
                ['BUDI SETIAWAN, S. Kom., MM', '197205312002121006'],
                ['IDAWATI, SH., MM', '197403231995032001'],
                ['ANDY DERMAWAN, ST., MM', '197508022006041002'],
                ['AGUS SETIAWAN, ST., MM', '197608312008041001'],
                ['LISTIYANI, S.IP., MM', '197406101994022002'],
                ['AGUS INDRA SURI, SE', '196908081990031009'],
                ['HESTI LARA RADIANI, SE', '198305042010012012'],
                ['SOFYANI, S. Kom', '199405032025041004'],
                ['SINTA WIDYANISA, S. Kom', '199404232025042001'],
                ['WASRONI', '198304162025211008'],
            ],
        ];
    }

    /**
     * Verify that every division referenced by the mapping actually exists.
     * Divisions are never created or modified here.
     */
    private function assertDivisionsExist(array $staff): void
    {
        $required = array_values(array_unique(self::DIVISION_BY_GROUP));
        sort($required);

        $existing = DB::table('divisions')
            ->whereIn('id', $required)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($existing !== $required) {
            throw new RuntimeException(
                'Missing division(s): expected IDs [' . implode(', ', $required)
                . '], found [' . implode(', ', $existing) . ']. Run DivisionSeeder first.'
            );
        }
    }

    /**
     * Fail fast if the hardcoded list ever contains a duplicate NIP.
     */
    private function assertNoDuplicateNip(array $staff): void
    {
        $nips = [];

        foreach ($staff as $members) {
            foreach ($members as [$name, $nip]) {
                $nips[] = $nip;
            }
        }

        $duplicates = array_keys(array_filter(array_count_values($nips), fn ($count) => $count > 1));

        if ($duplicates !== []) {
            throw new RuntimeException(
                'Duplicate NIP in staff list: ' . implode(', ', $duplicates)
            );
        }
    }

    /**
     * Fail fast if a NIP would collide with a username owned by a different
     * account (for example the `admin` account or a pre-existing legacy row).
     */
    private function assertNoUsernameConflict(array $staff): void
    {
        $nips = [];

        foreach ($staff as $members) {
            foreach ($members as [$name, $nip]) {
                $nips[] = $nip;
            }
        }

        $conflicts = User::whereIn('username', $nips)
            ->where(function ($query) use ($nips) {
                $query->whereNull('nip')->orWhereNotIn('nip', $nips);
            })
            ->pluck('username')
            ->all();

        if ($conflicts !== []) {
            throw new RuntimeException(
                'Username conflict, these usernames are already used by other accounts: '
                . implode(', ', $conflicts)
            );
        }
    }
}
