function MatchCard({ match }) {
  return (
    <article className="fixture">
      <div className="fixture-head">
        <span>{match.competition} &nbsp;·&nbsp; {match.stage}</span>
        <span className={`status${match.finished ? ' finished' : ''}`}><span aria-hidden="true">● &nbsp;</span>{match.status}</span>
      </div>

      <div className="match">
        <time className="time" dateTime={match.dateTime}>
          {match.time}
          <small>BRT</small>
        </time>

        <div className="teams" aria-label={`${match.home.name} contra ${match.away.name}`}>
          <div className="team">
            {match.home.crest ? <img className="crest" src={match.home.crest} alt={`Escudo do ${match.home.name}`} /> : <span className="crest" aria-label={`Escudo do ${match.home.name}`}>{match.home.abbreviation}</span>}
            <span className="team-name">{match.home.name}</span>
          </div>
          <span className="versus" aria-hidden="true">×</span>
          <div className="team">
            {match.away.crest ? <img className="crest" src={match.away.crest} alt={`Escudo do ${match.away.name}`} /> : <span className="crest" aria-label={`Escudo do ${match.away.name}`}>{match.away.abbreviation}</span>}
            <span className="team-name">{match.away.name}</span>
          </div>
        </div>
      </div>

      <div className="venue">
        <span aria-hidden="true">⌖ &nbsp;</span>{match.venue}
      </div>

      <div className="broadcasts">
        <p className="broadcast-title">Onde assistir</p>
        {match.broadcasts.length ? (
          <div className="broadcast-list">
            {match.broadcasts.map((broadcast) => (
              <span className="broadcast" key={broadcast.name}>
                <strong>{broadcast.name}</strong>
                <span className="category">{broadcast.category}</span>
                <span className={`access${broadcast.paid ? ' paid' : ''}`}>{broadcast.access}</span>
              </span>
            ))}
          </div>
        ) : (
          <p className="no-broadcast">Transmissão ainda não divulgada</p>
        )}
      </div>
    </article>
  );
}

export default MatchCard;
