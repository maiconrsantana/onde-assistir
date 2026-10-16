import Field from '../Field';

const options = [
  { value: '', label: 'Todas as competições' },
  { value: 'Campeonato Brasileiro Série A', label: 'Campeonato Brasileiro Série A' },
  { value: 'Copa do Brasil', label: 'Copa do Brasil' },
];

function Form({ filters, onFilterChange, onSubmit }) {
  return (
    <form className="filters" onSubmit={onSubmit} action="#proximos">
      <Field
        text="De"
        type="date"
        label="Data inicial"
        name="startDate"
        value={filters.startDate}
        onChange={onFilterChange}
        required
      />
      <Field
        text="Até"
        type="date"
        label="Data final"
        name="endDate"
        value={filters.endDate}
        onChange={onFilterChange}
        required
      />
      <Field
        text="Time"
        type="search"
        label="Time"
        name="team"
        placeholder="Ex.: Palmeiras"
        value={filters.team}
        onChange={onFilterChange}
      />
      <Field
        text="Competição"
        type="select"
        label="Competição"
        name="competition"
        value={filters.competition}
        onChange={onFilterChange}
        options={options}
      />
      <button type="submit">Buscar jogos</button>
    </form>
  );
}

export default Form;
