import React, { useState } from 'react';
import { Button } from '../ui/Button';
import { Label, Select, TextArea, TextInput } from '../ui/Field';
import { FieldError, FormAlert } from '../ui/FormMessage';
import { useLookups } from '../../hooks/useLookups';
import { useApiErrors } from '../../hooks/useApiErrors';
import { fieldErrorsOf } from '../../lib/apiErrors';
import type { Institute, InstituteInput } from '../../lib/api';
import { formatSriLankanMobile, toSriLankanMobile } from '../../lib/phone';

const blankToNull = (value: string) => value.trim() === '' ? null : value.trim();

/**
 * Institute details for POST /institutes (§5.1) and PATCH /institutes/{uuid} (§5.5). Districts come
 * from GET /lookups and are sent in their canonical spelling; the contact phone is normalised to
 * E.164. The API's own validation is final, and its field errors are shown next to each field.
 */
export function InstituteForm({
  initial,
  submitLabel,
  busyLabel,
  onSubmit,
  onCancel





}: {initial?: Institute | null;submitLabel: string;busyLabel: string;onSubmit: (input: InstituteInput) => Promise<void>;onCancel?: () => void;}) {
  const { districts, loading: lookupsLoading, failed: lookupsFailed } = useLookups();
  const describe = useApiErrors();
  const [name, setName] = useState(initial?.name ?? '');
  const [district, setDistrict] = useState(initial?.district ?? '');
  const [city, setCity] = useState(initial?.city ?? '');
  const [description, setDescription] = useState(initial?.description ?? '');
  const [phone, setPhone] = useState(initial?.contact_phone ? formatSriLankanMobile(initial.contact_phone) : '');
  const [email, setEmail] = useState(initial?.contact_email ?? '');
  const [regNo, setRegNo] = useState(initial?.business_reg_no ?? '');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const local: Record<string, string> = {};
    const contactPhone = phone.trim() === '' ? null : toSriLankanMobile(phone);

    if (name.trim() === '') local.name = 'Enter the institute’s name.';
    if (district === '') local.district = 'Choose a district.';
    if (phone.trim() !== '' && contactPhone === null) {
      local.contact_phone = 'Enter a Sri Lankan mobile number, for example 077 123 4567.';
    }
    setErrors(local);
    setMessage(null);
    if (Object.keys(local).length > 0) return;

    setBusy(true);
    try {
      await onSubmit({
        name: name.trim(),
        district,
        city: blankToNull(city),
        description: blankToNull(description),
        contact_phone: contactPhone,
        contact_email: blankToNull(email),
        business_reg_no: blankToNull(regNo)
      });
    } catch (error) {
      setErrors(fieldErrorsOf(error));
      setMessage(describe(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      <div>
        <Label htmlFor="inst-name">Institute name</Label>
        <TextInput
          id="inst-name"
          value={name}
          maxLength={255}
          onChange={(e) => setName(e.target.value)}
          aria-invalid={errors.name ? true : undefined}
          aria-describedby={errors.name ? 'inst-name-error' : undefined} />

        <FieldError id="inst-name-error" message={errors.name} />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <Label htmlFor="inst-district">District</Label>
          <Select
            id="inst-district"
            value={district}
            onChange={(e) => setDistrict(e.target.value)}
            disabled={lookupsLoading || lookupsFailed}
            aria-invalid={errors.district ? true : undefined}
            aria-describedby={errors.district ? 'inst-district-error' : undefined}>

            <option value="">{lookupsLoading ? 'Loading districts…' : 'Choose a district'}</option>
            {/* Keep a stored district visible even if the list has not loaded. */}
            {district && !districts.includes(district) ? <option value={district}>{district}</option> : null}
            {districts.map((d) =>
            <option key={d} value={d}>{d}</option>
            )}
          </Select>
          <FieldError
            id="inst-district-error"
            message={errors.district ?? (lookupsFailed ? 'Districts couldn’t be loaded. Refresh the page to try again.' : undefined)} />

        </div>
        <div>
          <Label htmlFor="inst-city" hint="Optional">City</Label>
          <TextInput
            id="inst-city"
            value={city}
            maxLength={100}
            onChange={(e) => setCity(e.target.value)}
            aria-invalid={errors.city ? true : undefined}
            aria-describedby={errors.city ? 'inst-city-error' : undefined} />

          <FieldError id="inst-city-error" message={errors.city} />
        </div>
      </div>

      <div>
        <Label htmlFor="inst-description" hint="Optional">About the institute</Label>
        <TextArea
          id="inst-description"
          rows={3}
          value={description}
          maxLength={5000}
          onChange={(e) => setDescription(e.target.value)}
          aria-invalid={errors.description ? true : undefined}
          aria-describedby={errors.description ? 'inst-description-error' : undefined} />

        <FieldError id="inst-description-error" message={errors.description} />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <Label htmlFor="inst-phone" hint="Optional">Contact mobile</Label>
          <TextInput
            id="inst-phone"
            inputMode="tel"
            autoComplete="off"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="077 123 4567"
            aria-invalid={errors.contact_phone ? true : undefined}
            aria-describedby={errors.contact_phone ? 'inst-phone-error' : undefined} />

          <FieldError id="inst-phone-error" message={errors.contact_phone} />
        </div>
        <div>
          <Label htmlFor="inst-email" hint="Optional">Contact email</Label>
          <TextInput
            id="inst-email"
            type="email"
            value={email}
            maxLength={255}
            onChange={(e) => setEmail(e.target.value)}
            aria-invalid={errors.contact_email ? true : undefined}
            aria-describedby={errors.contact_email ? 'inst-email-error' : undefined} />

          <FieldError id="inst-email-error" message={errors.contact_email} />
        </div>
      </div>

      <div>
        <Label htmlFor="inst-reg" hint="Optional">Business registration number</Label>
        <TextInput
          id="inst-reg"
          value={regNo}
          maxLength={100}
          onChange={(e) => setRegNo(e.target.value)}
          aria-invalid={errors.business_reg_no ? true : undefined}
          aria-describedby={errors.business_reg_no ? 'inst-reg-error' : undefined} />

        <FieldError id="inst-reg-error" message={errors.business_reg_no} />
      </div>

      <FormAlert message={message} />

      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={busy || lookupsLoading}>
          {busy ? busyLabel : submitLabel}
        </Button>
        {onCancel ?
        <Button type="button" variant="secondary" onClick={onCancel} disabled={busy}>
            Cancel
          </Button> :
        null}
      </div>
    </form>);

}
