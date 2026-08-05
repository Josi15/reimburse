import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import SelectInput from '@/Components/ui/SelectInput';
import TextareaInput from '@/Components/ui/TextareaInput';

/**
 * Merender field tambahan sesuai jenis pengajuan. Definisi field datang dari
 * backend (GET /api/options/claim-types → enum ClaimType), jadi menambah jenis
 * atau field baru cukup di PHP — komponen ini tidak perlu diubah.
 */
export default function ClaimTypeFields({ type, values, onChange, errors }) {
    // Field bertanda `hidden` diisi server dari profil user (mis. upah lembur
    // menurut jabatan) dan sengaja tidak ditampilkan di form.
    const fields = (type?.fields ?? []).filter((f) => !f.hidden);

    if (fields.length === 0) return null;

    return (
        <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                {type.icon} Detail {type.label}
            </h3>
            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {type.description}
            </p>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                {fields.map((field) => {
                    const id = `detail-${field.key}`;
                    const error = errors[`details.${field.key}`]?.[0];
                    const wide = field.type === 'textarea';
                    // Field bersumber-server (mis. upah lembur menurut jabatan)
                    // tampil terkunci; server menimpanya lagi saat menyimpan.
                    const locked = field.readonly === true;
                    const value = locked
                        ? (field.fixed_value ?? '')
                        : (values[field.key] ?? '');

                    return (
                        <div
                            key={field.key}
                            className={wide ? 'sm:col-span-2' : ''}
                        >
                            <InputLabel
                                htmlFor={id}
                                value={`${field.label}${field.required ? ' *' : ''}`}
                            />

                            {field.type === 'textarea' ? (
                                <TextareaInput
                                    id={id}
                                    rows={2}
                                    className="mt-1 block w-full"
                                    value={value}
                                    onChange={(e) =>
                                        onChange(field.key, e.target.value)
                                    }
                                />
                            ) : field.type === 'select' ? (
                                <SelectInput
                                    id={id}
                                    className="mt-1 block w-full"
                                    value={value}
                                    onChange={(e) =>
                                        onChange(field.key, e.target.value)
                                    }
                                >
                                    <option value="">— pilih —</option>
                                    {field.options.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </SelectInput>
                            ) : (
                                <div className="relative mt-1">
                                    <TextInput
                                        id={id}
                                        type={
                                            field.type === 'number'
                                                ? 'number'
                                                : field.type
                                        }
                                        min={field.min}
                                        step={field.step}
                                        disabled={locked}
                                        className={
                                            'block w-full disabled:bg-gray-100 disabled:text-gray-500 dark:disabled:bg-gray-900/50' +
                                            (field.suffix ? ' pr-14' : '')
                                        }
                                        value={value}
                                        onChange={(e) =>
                                            !locked &&
                                            onChange(field.key, e.target.value)
                                        }
                                    />
                                    {field.suffix && (
                                        <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-gray-400">
                                            {field.suffix}
                                        </span>
                                    )}
                                </div>
                            )}

                            {field.help && (
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {field.help}
                                </p>
                            )}
                            <InputError message={error} className="mt-1" />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
