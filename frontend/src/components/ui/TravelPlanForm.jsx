// src/components/travel-plan/TravelPlanForm.jsx
import React from "react";
import FormInput from "../ui/FormInput";
import SelectInput from "../ui/SelectInput";
import CheckboxGroup from "../ui/CheckboxGroup";
import PrimaryButton from "../ui/PrimaryButton";

/**
 * Reusable forma za kreiranje/izmene Travel plana.
 * Sav vidljivi tekst je na ENGLESKOM; komentari su na SRPSKOM.
 *
 * Props:
 * - mode: "create" | "edit"
 * - initialValues: objekat sa vrednostima forme
 * - lockedFields: string[] (imena polja koja su zaključana i prikazuju se read-only)
 * - lists:
 *    - destinations: string[] ili [{value,label}]
 *    - transportModes: string[]
 *    - accommodationOptions: string[] ili [{value,label}]
 *    - preferencesList: string[]
 * - busy: bool (disable submit)
 * - fieldErrors: { [name]: string }
 * - error: string (global error message)
 * - onSubmit(values)
 * - onCancel()
 */
export default function TravelPlanForm({
  mode = "create",
  initialValues,
  lockedFields = [],
  lists = {},
  busy = false,
  fieldErrors = {},
  error = "",
  onSubmit,
  onCancel,
}) {
  //   lokalni state forme
  const [form, setForm] = React.useState({
    start_location: "",
    destination: "",
    start_date: "",
    end_date: "",
    passenger_count: 1,
    budget: "",
    preferences: [],
    transport_mode: "",
    accommodation_class: "",
    ...initialValues,
  });

  React.useEffect(() => {
    setForm((f) => {
      const next = { ...f };

      if (!next.start_location && (lists.startLocations?.length ?? 0) > 0) {
        next.start_location = lists.startLocations[0]; // statičan niz koji šalješ iz CreateTravelPlan
      }

      if (!next.destination && (lists.destinations?.length ?? 0) > 0) {
        const first = lists.destinations[0];
        next.destination = typeof first === "string" ? first : first.value;
      }

      return next;
    });
  }, [lists.startLocations, lists.destinations]);

  //   helperi za promenu polja
  const onChange = (e) => {
    const { name, value } = e.target;
    setForm((f) => ({ ...f, [name]: value }));
  };
  const onChangeNumber = (e) => {
    const { name, value } = e.target;
    setForm((f) => ({ ...f, [name]: value === "" ? "" : Number(value) }));
  };
  const onChangePrefs = (vals) => {
    setForm((f) => ({ ...f, preferences: vals }));
  };

  //   read-only render helper
  const isLocked = (name) => lockedFields.includes(name);

  //   male util funkcije za listu (dozvoljeno je proslediti niz stringova ili {value,label})
  const toOptions = (arr) =>
    (arr || []).map((x) =>
      typeof x === "string" ? { value: x, label: humanize(x) } : x
    );

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        onSubmit?.(form);
      }}
      noValidate
      className={`tp-form ${mode === "edit" ? "edit-mode" : "create-mode"}`}
    >
      {error && <div className="alert alert-danger mb-3">{error}</div>}

      {/* ========== READ-ONLY BLOK (zaključana polja) ========== */}
      {lockedFields.length > 0 && (
        <fieldset disabled className="mb-4">
          <div className="row g-3">
            {isLocked("start_location") && (
              <div className="col-md-6">
                <FormInput
                  label="Start location"
                  name="start_location"
                  disabled
                  defaultValue={form.start_location ?? ""}
                />
              </div>
            )}
            {isLocked("destination") && (
              <div className="col-md-6">
                <FormInput
                  label="Destination"
                  name="destination"
                  disabled
                  defaultValue={form.destination ?? ""}
                />
              </div>
            )}
            {isLocked("transport_mode") && (
              <div className="col-md-6">
                <FormInput
                  label="Transport mode"
                  name="transport_mode"
                  disabled
                  defaultValue={humanize(form.transport_mode ?? "")}
                />
              </div>
            )}
            {isLocked("accommodation_class") && (
              <div className="col-md-6">
                <FormInput
                  label="Accommodation class"
                  name="accommodation_class"
                  disabled
                  defaultValue={humanize(form.accommodation_class ?? "")}
                />
              </div>
            )}
            {isLocked("preferences") && (
              <div className="col-12">
                <FormInput
                  label="Preferences"
                  name="preferences"
                  disabled
                  defaultValue={(form.preferences || [])
                    .map(humanize)
                    .join(", ")}
                />
              </div>
            )}
          </div>
        </fieldset>
      )}

      {/* ========== EDITABLE BLOK ========== */}
      <div className="row g-3">
        {/* Start location / Destination – samo ako NISU zaključani (tj. na Create) */}
        {!isLocked("start_location") && (
          <div className="col-md-6">
            <SelectInput
              id="start_location"
              name="start_location"
              label="Start location"
              value={form.start_location}
              onChange={(e) =>
                setForm((f) => ({ ...f, start_location: e.target.value }))
              }
              options={(lists.startLocations || []).map((v) => ({
                value: v,
                label: v,
              }))}
              placeholder="Select start location"
              error={fieldErrors.start_location}
              required
            />
          </div>
        )}
        {!isLocked("destination") && (
          <div className="col-md-6">
            <SelectInput
              id="destination"
              name="destination"
              label="Destination"
              value={form.destination}
              onChange={(e) =>
                setForm((f) => ({ ...f, destination: e.target.value }))
              }
              options={toOptions(lists.destinations)}
              placeholder="Select destination"
              error={fieldErrors.destination}
              required
            />
          </div>
        )}

        <div className="col-md-6">
          <FormInput
            id="start_date"
            name="start_date"
            type="date"
            label="Start date"
            value={form.start_date}
            onChange={onChange}
            error={fieldErrors.start_date}
            required
          />
        </div>

        <div className="col-md-6">
          <FormInput
            id="end_date"
            name="end_date"
            type="date"
            label="End date"
            value={form.end_date}
            onChange={onChange}
            error={fieldErrors.end_date}
            required
          />
        </div>

        <div className="col-md-4">
          <FormInput
            id="passenger_count"
            name="passenger_count"
            type="number"
            label="Number of travelers"
            placeholder="1"
            value={form.passenger_count}
            onChange={onChangeNumber}
            error={fieldErrors.passenger_count}
            min={1}
            required
          />
        </div>

        <div className="col-md-4">
          <FormInput
            id="budget"
            name="budget"
            type="number"
            step="0.01"
            label="Available budget (USD)"
            placeholder="e.g., 1200.00"
            value={form.budget}
            onChange={onChangeNumber}
            error={fieldErrors.budget}
            min={1}
            required
          />
        </div>

        {/* Transport / Accommodation – samo ako NISU zaključani (tj. na Create) */}
        {!isLocked("transport_mode") && (
          <div className="col-md-4">
            <SelectInput
              id="transport_mode"
              label="Transport mode"
              value={form.transport_mode}
              onChange={(e) =>
                setForm((f) => ({ ...f, transport_mode: e.target.value }))
              }
              options={toOptions(lists.transportModes)}
              placeholder="Select transport"
              error={fieldErrors.transport_mode}
              required
            />
          </div>
        )}

        {!isLocked("accommodation_class") && (
          <div className="col-12">
            <SelectInput
              id="accommodation_class"
              label="Accommodation class"
              value={form.accommodation_class}
              onChange={(e) =>
                setForm((f) => ({
                  ...f,
                  accommodation_class: e.target.value,
                }))
              }
              options={toOptions(lists.accommodationOptions)}
              placeholder="Select accommodation"
              error={fieldErrors.accommodation_class}
              required
            />
          </div>
        )}

        {!isLocked("preferences") && (
          <div className="col-12">
            <CheckboxGroup
              legend="Preferences"
              name="preferences"
              items={lists.preferencesList || []}
              values={form.preferences}
              onChange={onChangePrefs}
              error={fieldErrors.preferences || fieldErrors["preferences.*"]}
            />
          </div>
        )}
      </div>

      <div className="d-flex gap-2 mt-3">
        <PrimaryButton type="submit" disabled={busy}>
          {busy
            ? mode === "edit"
              ? "Updating..."
              : "Creating..."
            : mode === "edit"
            ? "Update"
            : "Create plan"}
        </PrimaryButton>
        <button
          type="button"
          className="btn btn-outline-secondary"
          onClick={onCancel}
        >
          Cancel
        </button>
      </div>
    </form>
  );
}

//   sitni helper za prikaz labela iz snake_case
function humanize(s) {
  return String(s)
    .replace(/_/g, " ")
    .replace(/\b[a-z]/g, (c) => c.toUpperCase());
}
