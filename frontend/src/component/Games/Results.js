import MatchCard from './MatchCard';

function Results({ matches }) {
  const days = matches.reduce((groups, match) => {
    const group = groups.find((item) => item.date === match.date);
    if (group) {
      group.matches.push(match);
    } else {
      groups.push({ date: match.date, label: match.dayLabel, matches: [match] });
    }
    return groups;
  }, []);

  return (
    <>
      <div className="results" aria-live="polite">
        <span>
          <strong>{matches.length}</strong>{' '}
          {matches.length === 1 ? 'partida encontrada' : 'partidas encontradas'}
        </span>
        <span className="demo-label"><span aria-hidden="true">● &nbsp;</span>Dados publicados pela API</span>
      </div>

      {days.length ? days.map((day) => (
        <section className="day" key={day.date} aria-label={day.label}>
          <h2 className="day-title">
            <span>{day.label}</span>
            <small>{day.matches.length} {day.matches.length === 1 ? 'jogo' : 'jogos'}</small>
          </h2>
          {day.matches.map((match) => <MatchCard key={match.id} match={match} />)}
        </section>
      )) : (
        <p className="empty-results" role="status">
          Nenhuma partida encontrada com esses filtros. Ajuste os critérios e tente novamente.
        </p>
      )}
    </>
  );
}

export default Results;
