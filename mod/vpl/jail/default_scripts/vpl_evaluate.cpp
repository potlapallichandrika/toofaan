/**
 * VPL builtin program for submissions evaluation
 * @Copyright (C) 2019 Juan Carlos Rodríguez-del-Pino
 * @License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @Author Juan Carlos Rodríguez-del-Pino <jcrodriguez@dis.ulpgc.es>
 */

#include <cstdlib>
#include <cstdio>
#include <climits>
#include <limits>
#include <errno.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <poll.h>
#include <unistd.h>
#include <pty.h>
#include <fcntl.h>
#include <signal.h>
#include <cstring>
#include <string>
#include <iostream>
#include <sstream>
#include <vector>
#include <cmath>
#include <execinfo.h>
#include <regex.h>
#include <string>
#include <sys/time.h>

using namespace std;

const int MAXCOMMENTS = 20;
const int MAXCOMMENTSLENGTH = 100*1024;
const int MAXCOMMENTSTITLELENGTH = 1024;
const int MAXOUTPUT = 256* 1024 ;//256Kb

/**
 * Class Tools Declaration
 */
class Tools {
public:
	static bool existFile(string name);
	static string readFile(string name);
	static vector<string> splitLines(const string &data);
	static int nextLine(const string &data);
	static string caseFormat(string text);
	static string toLower(const string &text);
	static string normalizeTag(const string &text);
	static bool parseLine(const string &text, string &name, string &data);
	static string trimRight(const string &text);
	static string trim(const string &text);
	static void fdblock(int fd, bool set);
	static bool convert2(const string& str, double &data);
	static bool convert2(const string& str, long int &data);
	static const char* getenv(const char* name, const char* defaultvalue);
	static double getenv(const char* name, double defaultvalue);
};

/**
 * Class Stop Declaration
 */
class Stop{
	static volatile bool TERMRequested;
public:
	static void setTERMRequested();
	static bool isTERMRequested();
};

/**
 * Class Timer Declaration
 */
class Timer{
	static time_t startTime;
public:
	static void start();
	static int elapsedTime();
};

/**
 * Class I18n Declaration
 */
class I18n{
public:
	void init();
	const char *get_string(const char *s);
};

/**
 * Interface OutputChecker
 */
class OutputChecker{
protected:
	string text;

public:
	OutputChecker(const string &t):text(t){}
	virtual ~OutputChecker(){};
	virtual string type(){return "";}
	virtual operator string (){return "";}
	virtual string outputExpected(){return text;}
	virtual string studentOutputExpected(){return text;}
	virtual bool match(const string&)=0;
	virtual OutputChecker* clone()=0;
};

/**
 * Class NumbersOutput Declaration
 */
class NumbersOutput:public OutputChecker{
	struct Number{
		bool isInteger;
		long int integer;
		double cientific;

		bool set(const string& str);
		bool operator==(const Number &o)const;
		bool operator!=(const Number &o)const;
		operator string () const;
	};

	vector<Number> numbers;
	bool startWithAsterisk;
	string cleanText;

	static bool isNum(char c);
	static bool isNumStart(char c);
	bool calcStartWithAsterisk();

public:
	NumbersOutput(const string &text);//:OutputChecker(text);
	string studentOutputExpected();
	bool operator==(const NumbersOutput& o)const;
	bool match(const string& output);
	OutputChecker* clone();
	static bool typeMatch(const string& text);
	string type();
	operator string () const;
};

/**
 * Class TextOutput Declaration
 */
class TextOutput:public OutputChecker{
	vector<string> tokens;
	bool isAlpha(char c);

public:
	TextOutput(const string &text);//:OutputChecker(text);
	bool operator==(const TextOutput& o);
	bool match(const string& output);
	OutputChecker* clone();
	static bool typeMatch(const string& text);
	string type();
};

/**
 * Class ExactTextOutput Declaration
 */
class ExactTextOutput:public OutputChecker{
	string cleanText;
	bool startWithAsterix;
	bool isAlpha(char c);

public:
	ExactTextOutput(const string &text);//:OutputChecker(text);
	string studentOutputExpected();
	bool operator==(const ExactTextOutput& o);
	bool match(const string& output);
	OutputChecker* clone();
	static bool typeMatch(const string& text);
	string type();
};

/**
 * Class RegularExpressionOutput Declaration
 * Regular Expressions implemented by:
 * Daniel José Ojeda Loisel
 * Juan David Vega Rodríguez
 * Miguel Ángel Viera González
 */
class RegularExpressionOutput:public OutputChecker {
	string errorCase;
	string cleanText;
	regex_t expression;
	bool flagI;
	bool flagM;
	int reti;

public:
	RegularExpressionOutput (const string &text, const string &actualCaseDescription);

	bool match (const string& output);
		// Regular Expression compilation (with flags in mind) and comparison with the input and output evaluation

	string studentOutputExpected();
		// Returns the expression without flags nor '/'

	OutputChecker* clone();

	static bool typeMatch(const string& text);
		// Tests if it's a regular expression. A regular expressions should be between /../

	string type();
};
/**
 * Class Case Declaration
 * Case represents cases
 */
class Case {
	string input;
	vector< string > output;
	string caseDescription;
	float gradeReduction;
	string failMessage;
	string programToRun;
	string programArgs;
	int expectedExitCode; // Default value std::numeric_limits<int>::min()
	string variation;
public:
	Case();
	void reset();
	void addInput(string );
	string getInput();
	void addOutput(string );
	const vector< string > & getOutput();
	void setFailMessage(const string &);
	string getFailMessage();
	void setCaseDescription(const string &);
	string getCaseDescription();
	void setGradeReduction(float);
	float getGradeReduction();
	void setExpectedExitCode(int);
	int getExpectedExitCode();
	void setProgramToRun(const string &);
	string getProgramToRun();
	void setProgramArgs(const string &);
	string getProgramArgs();
	void setVariation(const string &);
	string getVariation();
};

/**
 * Class TestCase Declaration
 * TestCase represents cases to tested
 */
class TestCase {
	const char *command;
	const char **argv;
	static const char **envv;
	int id;
	bool correctOutput;
	bool outputTooLarge;
	bool programTimeout;
	bool executionError;
	bool correctExitCode;
	char executionErrorReason[1000];
	int sizeReaded;
	string input;
	vector< OutputChecker* > output;
	string caseDescription;
	float gradeReduction;
	float gradeReductionApplied;
	string failMessage;
	string programToRun;
	string programArgs;
	string variantion;
	int expectedExitCode; // Default value std::numeric_limits<int>::min()
	int exitCode; // Default value std::numeric_limits<int>::min()
	string programOutputBefore, programOutputAfter, programInput;
	long long executionTimeNs;

	void cutOutputTooLarge(string &output);
	void readWrite(int fdread, int fdwrite);
	void addOutput(const string &o, const string &actualCaseDescription);
public:
	static void setEnvironment(const char **environment);
	void setDefaultCommand();
	TestCase(const TestCase &o);
	TestCase& operator=(const TestCase &o);
	~TestCase();
	TestCase(int id, const string &input, const vector<string> &output,
			const string &caseDescription, const float gradeReduction,
		    string failMessage, string programToRun, string programArgs, int expectedExitCode);
	bool isCorrectResult();
	bool isExitCodeTested();
	float getGradeReduction();
	void setGradeReductionApplied(float r);
	float getGradeReductionApplied();
	string getCaseDescription();
	string getCommentTitle(bool withGradeReduction/*=false*/); // Suui
	string getComment();
	void splitArgs(string);
	void runTest(time_t timeout);
	bool match(string data);
	long long getExecutionTimeNs() const;
};

/**
 * Class Evaluation Declaration
 */
class Evaluation {
	int maxtime,executiontime,ttime,tletime,tleflag,showcom;
	float grademin, grademax;
	string variation;
	bool noGrade;
	float grade;
	int nerrors, nruns;
	long long totalExecutionTimeNs;
	long long tleLimitNs;
	//long long tletime;
	bool tleFlag;
	vector<TestCase> testCases;
	char comments[MAXCOMMENTS + 1][MAXCOMMENTSLENGTH + 1];
	char titles[MAXCOMMENTS + 1][MAXCOMMENTSTITLELENGTH + 1];
	char titlesGR[MAXCOMMENTS + 1][MAXCOMMENTSTITLELENGTH + 1];
	volatile int ncomments;
	volatile bool stopping;
	static Evaluation *singlenton;
	Evaluation();

public:
	static Evaluation* getSinglenton();
	static void deleteSinglenton();
	void addTestCase(const string &input,
                 const vector<string> &output,
                 const string &caseDescription,
                 float gradeReduction);
	//void addTestCase(Case &caso);
	void removeLastNL(string &s);
	bool cutToEndTag(string &value, const string &endTag);
	void loadTestCases(string fname);
	bool loadParams();
	void addFatalError(const char *m);
	void runTests();
	void outputEvaluation();
};

/////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////// END OF DECLARATIONS ///////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////

/////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////// BEGINNING OF DEFINITIONS ////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////

volatile bool Stop::TERMRequested = false;
time_t Timer::startTime;
const char **TestCase::envv=NULL;
Evaluation* Evaluation::singlenton = NULL;

/**
 * Class Tools Definitions
 */

bool Tools::existFile(string name) {
	FILE *f = fopen(name.c_str(), "r");
	if (f != NULL) {
		fclose(f);
		return true;
	}
	return false;
}

string Tools::readFile(string name) {
	char buf[1000];
	string res;
	FILE *f = fopen(name.c_str(), "r");
	if (f != NULL)
		while (fgets(buf, 1000, f) != NULL)
			res += buf;
	return res;
}

vector<string> Tools::splitLines(const string &data) {
	vector<string> lines;
	int len, l = data.size();
	int startLine = 0;
	char pc = 0, c;
	for (int i = 0; i < l; i++) {
		c = data[i];
		if (c == '\n') {
			len = i - startLine;
			if (pc == '\r')
				len--;
			lines.push_back(data.substr(startLine, len));
			startLine = i + 1;
		}
		pc = c;
	}
	if (startLine < l) {
		len = l - startLine;
		if (pc == '\r')
			len--;
		lines.push_back(data.substr(startLine, len));
	}
	return lines;
}

int Tools::nextLine(const string &data) {
	int l = data.size();
	for (int i = 0; i < l; i++) {
		if (data[i] == '\n')
			return i + 1;
	}
	return l;
}

string Tools::caseFormat(string text) {
	vector<string> lines = Tools::splitLines(text);
	string res;
	int nlines = lines.size();
	for (int i = 0; i < nlines; i++)
		res += ">" + lines[i] + '\n';
	return res;
}

bool Tools::parseLine(const string &text, string &name, string &data) {
	size_t poseq;
	if ((poseq = text.find('=')) != string::npos) {
		name = normalizeTag(text.substr(0, poseq + 1));
		data = text.substr(poseq + 1);
		return true;
	}
	name = "";
	data = text;
	return false;
}

string Tools::toLower(const string &text) {
	string res = text;
	int len = res.size();
	for (int i = 0; i < len; i++)
		res[i] = tolower(res[i]);
	return res;
}

string Tools::normalizeTag(const string &text) {
	string res;
	int len = text.size();
	for (int i = 0; i < len; i++) {
		char c = text[i];
		if (isalpha(c) || c == '=')
			res += tolower(c);
	}
	return res;
}

string Tools::trimRight(const string &text) {
	int len = text.size();
	int end = -1;
	for (int i = len - 1; i >= 0; i--) {
		if (!isspace(text[i])) {
			end = i;
			break;
		}
	}
	return text.substr(0, end + 1);
}

string Tools::trim(const string &text) {
	int len = text.size();
	int begin = len;
	int end = -1;
	for (int i = 0; i < len; i++) {
		char c = text[i];
		if (!isspace(c)) {
			begin = i;
			break;
		}
	}
	for (int i = len - 1; i >= 0; i--) {
		char c = text[i];
		if (!isspace(c)) {
			end = i;
			break;
		}
	}
	if (begin <= end)
		return text.substr(begin, (end - begin) + 1);
	return "";
}

void Tools::fdblock(int fd, bool set) {
	int flags;
	if ((flags = fcntl(fd, F_GETFL, 0)) < 0) {
		return;
	}
	if (set && (flags | O_NONBLOCK) == flags)
		flags ^= O_NONBLOCK;
	else
		flags |= O_NONBLOCK;
	fcntl(fd, F_SETFL, flags);
}

bool Tools::convert2(const string& str, double &data){
	if ( str == "." ){
		return false;
	}
	stringstream conv(str);
	conv >> data;
	return conv.eof();
}

bool Tools::convert2(const string& str, long int &data){
	stringstream conv(str);
	conv >> data;
	return conv.eof();
}
const char* Tools::getenv(const char* name, const char* defaultvalue) {
	const char* value = ::getenv(name);
	if ( value == NULL ) {
		value = defaultvalue;
	    printf("Warning: using default value '%s' for '%s'\n", defaultvalue, name);
	}
	return value; // Fixes bug found by Peter Svec
}

double Tools::getenv(const char* name, double defaultvalue) {
	const char* svalue = ::getenv(name);
	double value = defaultvalue;
	if ( svalue != NULL ) {
		Tools::convert2(svalue, value);
	} else {
		printf("Warning: using default value '%lf' for '%s'\n", defaultvalue, name);
	}
	return value;
}


/**
 * Class Stop Definitions
 */

void Stop::setTERMRequested() {
	TERMRequested = true;
}

bool Stop::isTERMRequested() {
	return TERMRequested;
}

/**
 * Class Timer Definitions
 */

void Timer::start() {
	startTime = time(NULL);
}

int Timer::elapsedTime() {
	return time(NULL) - startTime;
}

/**
 * Class Stop Definitions
 */

void I18n::init(){

}

const char *I18n::get_string(const char *s){
	return s;
}

/**
 * Class NumbersOutput Definitions
 */

// Struct Number
bool NumbersOutput::Number::set(const string& str){
	isInteger=Tools::convert2(str, integer);
	if(!isInteger){
		return Tools::convert2(str, cientific);
	}
	return true;
}

bool NumbersOutput::Number::operator==(const Number &o)const{
	if(isInteger)
		return o.isInteger && integer == o.integer;
	if(o.isInteger)
		return cientific != 0?fabs((cientific - o.integer) / cientific) < 0.0001 : o.integer == 0;
	else
		return cientific != 0?fabs((cientific - o.cientific) / cientific) < 0.0001 : fabs(o.cientific) < 0.0001;
}

bool NumbersOutput::Number::operator!=(const Number &o)const{
	return !((*this)==o);
}

NumbersOutput::Number::operator string() const{
	char buf[100];
	if(isInteger) {
		sprintf(buf, "%ld", integer);
	} else {
		sprintf(buf, "%10.5lf", cientific);
	}
	return buf;
}


bool NumbersOutput::isNum(char c){
	if(isdigit(c)) return true;
	return c=='+' || c=='-' || c=='.' || c=='e' || c=='E';
}

bool NumbersOutput::isNumStart(char c){
	if(isdigit(c)) return true;
	return c=='+' || c=='-' || c=='.';
}

bool NumbersOutput::calcStartWithAsterisk(){
	int l=text.size();
	for(int i=0; i<l; i++){
		char c=text[i];
		if(isspace(c)) continue;
		if(c=='*'){
			cleanText = text.substr(i+1,text.size()-(i+1));
			return true;
		}else{
			cleanText = text.substr(i,text.size()-i);
			return false;
		}
	}
	return false;
}

NumbersOutput::NumbersOutput(const string &text):OutputChecker(text){
	int l=text.size();
	string str;
	Number number;
	for(int i=0; i<l; i++){
		char c=text[i];
		if((isNum(c) && str.size()>0) || (isNumStart(c) && str.size()==0)){
			str+=c;
		}else if(str.size()>0){
			if(isNumStart(str[0]) && number.set(str)) numbers.push_back(number);
			str="";
		}
	}
	if(str.size()>0){
		if(isNumStart(str[0]) && number.set(str)) numbers.push_back(number);
	}
	startWithAsterisk=calcStartWithAsterisk();
}

string NumbersOutput::studentOutputExpected(){
	return cleanText;
}

bool NumbersOutput::operator==(const NumbersOutput& o)const{
	size_t l=numbers.size();
	if( o.numbers.size() < l ) return false;
	int offset = 0;
	if(startWithAsterisk)
		offset = o.numbers.size()-l;
	for(size_t i = 0; i < l; i++)
		if(numbers[i] != o.numbers[offset+i])
			return false;
	return true;
}

bool NumbersOutput::match(const string& output){
	NumbersOutput temp(output);
	return operator==(temp);
}

OutputChecker* NumbersOutput::clone(){
	return new NumbersOutput(outputExpected());
}

bool NumbersOutput::typeMatch(const string& text){
	int l=text.size();
	string str;
	Number number;
	for(int i=0; i<l; i++){
		char c=text[i];
		// Skip spaces/CR/LF... and *
		if(!isspace(c) && c!='*') {
			str += c;
		}else if(str.size()>0) {
			if (!isNumStart(str[0])||
				!number.set(str)) return false;
			str="";
		}
	}
	if(str.size()>0){
		if(!isNumStart(str[0])||!number.set(str)) return false;
	}
	return true;
}

string NumbersOutput::type(){
	return "numbers";
}

NumbersOutput::operator string () const{
	string ret="[";
	int l=numbers.size();
	for(int i=0; i<l; i++){
		ret += i > 0 ? ", " : "";
		ret += numbers[i];
	}
	ret += "]";
	return ret;
}

/**
 * Class TextOutput Definitions
 */

bool TextOutput::isAlpha(char c){
	if ( isalnum(c) ) return true;
	return c < 0;
}

TextOutput::TextOutput(const string &text):OutputChecker(text){
	size_t l = text.size();
	string token;
	for(size_t i = 0; i < l; i++){
		char c = text[i];
		if( isAlpha(c) ){
			token += c;
		}else if(token.size() > 0){
			tokens.push_back(Tools::toLower(token));
			token="";
		}
	}
	if(token.size()>0){
		tokens.push_back(Tools::toLower(token));
	}
}

bool TextOutput::operator==(const TextOutput& o) {
	size_t l = tokens.size();
	if (o.tokens.size() < l) return false;
	int offset = o.tokens.size() - l;
	for (size_t i = 0; i < l; i++)
		if (tokens[i] != o.tokens[ offset + i ])
			return false;
	return true;
}

bool TextOutput::match(const string& output) {
	TextOutput temp(output);
	return operator== (temp);
}

OutputChecker* TextOutput::clone() {
	return new TextOutput(outputExpected());
}

bool TextOutput::typeMatch(const string& text) {
	return true;
}

string TextOutput::type(){
	return "text";
}

/**
 * Class ExactTextOutput Definitions
 */

bool ExactTextOutput::isAlpha(char c){
	if(isalnum(c)) return true;
	return c < 0;
}

ExactTextOutput::ExactTextOutput(const string &text):OutputChecker(text){
	string clean = Tools::trim(text);
	if(clean.size() > 2 && clean[0] == '*') {
		startWithAsterix = true;
		cleanText = clean.substr(2, clean.size() - 3);
	}else{
		startWithAsterix =false;
		cleanText=clean.substr(1,clean.size()-2);
	}
}

string ExactTextOutput::studentOutputExpected(){
	return cleanText;
}

bool ExactTextOutput::operator==(const ExactTextOutput& o){
	return match(o.text);
}

bool ExactTextOutput::match(const string& output){
	if(cleanText.size()==0 && output.size()==0) return true;
	string clean;
	// Clean output if text last char is alpha
	if(cleanText.size()>0 && isAlpha(cleanText[cleanText.size()-1])){
		clean=Tools::trimRight(output);
	}else{
		clean=output;
	}
	if(startWithAsterix){
		size_t start=clean.size()-cleanText.size();
		return cleanText.size()<=clean.size() &&
				cleanText == clean.substr(start,cleanText.size());
	}
	else
		return cleanText==clean;
}

OutputChecker* ExactTextOutput::clone(){
	return new ExactTextOutput(outputExpected());
}

bool ExactTextOutput::typeMatch(const string& text){
	string clean=Tools::trim(text);
	return (clean.size()>1 && clean[0]=='"' && clean[clean.size()-1]=='"')
			||(clean.size()>3 && clean[0]=='*' && clean[1]=='"' && clean[clean.size()-1]=='"');
}

string ExactTextOutput::type(){
	return "exact text";
}

/**
 * Class RegularExpressionOutput Definitions
 */

RegularExpressionOutput::RegularExpressionOutput(const string &text, const string &actualCaseDescription):OutputChecker(text) {

	errorCase = actualCaseDescription;
	size_t pos = 1;
	flagI = false;
	flagM = false;
	string clean = Tools::trim(text);

	while (clean[pos] != '/' && pos < clean.size()) {
		pos++;
	}
	cleanText = clean.substr(1,pos-1);
	if (pos + 1 != clean.size()) {
		pos = pos + 1;

		// Flag search
		while (pos < clean.size()) {

			switch (clean[pos]) {
				case 'i':
					flagI=true;
					break;
				case 'm':
					flagM=true;
					break;
				default:
					Evaluation* p_ErrorTest = Evaluation::getSinglenton();
					char wrongFlag = clean[pos];
					string flagCatch;
					stringstream ss;
					ss << wrongFlag;
					ss >> flagCatch;
					string errorType = string("Flag Error in case ")+ string(errorCase)+ string (", found a ") + string(flagCatch) + string (" used as a flag, only i and m available");
					const char* flagError = errorType.c_str();
					p_ErrorTest->addFatalError(flagError);
					p_ErrorTest->outputEvaluation();
					abort();
			}
			pos++;
		}
	}
}

// Regular Expression compilation (with flags in mind) and comparison with the input and output evaluation
bool RegularExpressionOutput::match (const string& output) {

	reti=-1;
	const char * in = cleanText.c_str();
	// Use POSIX-C regrex.h
	// Flag compilation
	if (flagI || flagM) {
		if (flagM && flagI) {
			reti = regcomp(&expression, in, REG_EXTENDED | REG_NEWLINE | REG_ICASE);
		} else if (flagM) {
			reti = regcomp(&expression, in, REG_EXTENDED | REG_NEWLINE);
		} else {
			reti = regcomp(&expression, in, REG_EXTENDED | REG_ICASE);
		}

	// No flag compilation
	} else {
		reti = regcomp(&expression, in, REG_EXTENDED);
	}

	if (reti == 0) { // Compilation was successful

		const char * out = output.c_str();
		reti = regexec(&expression, out, 0, NULL, 0);

		if (reti == 0) { // Match
			return true;
		} else if (reti == REG_NOMATCH){ // No match
			return false;

		} else { // Memory Error
			Evaluation* p_ErrorTest = Evaluation::getSinglenton();
			string errorType = string("Out of memory error, during matching case ") + string(errorCase);
			const char* flagError = errorType.c_str();
			p_ErrorTest->addFatalError(flagError);
			p_ErrorTest->outputEvaluation();
			abort();
		}

	} else { // Compilation error
		size_t length = regerror(reti, &expression, NULL, 0);
        char* bff = new char[length + 1];
        (void) regerror(reti, &expression, bff, length);
		Evaluation* p_ErrorTest = Evaluation::getSinglenton();
		string errorType = string("Regular Expression compilation error")+string (" in case: ")+ string(errorCase) +string (".\n")+ string(bff);
		const char* flagError = errorType.c_str();
		p_ErrorTest->addFatalError(flagError);
		p_ErrorTest->outputEvaluation();
		abort();
		return false;
	}
}

// Returns the expression without flags nor '/'
string RegularExpressionOutput::studentOutputExpected() {return cleanText;}

OutputChecker* RegularExpressionOutput::clone() {
	return new RegularExpressionOutput(outputExpected(), errorCase);
}

// Tests if it's a regular expression. A regular expressions should be between /../
bool RegularExpressionOutput::typeMatch(const string& text) {
	string clean=Tools::trim(text);
	if (clean.size() > 2 && clean[0] == '/') {
		for (size_t i = 1; i < clean.size(); i++) {
			if (clean[i] == '/') {
				return true;
			}
		}
	}
	return false;
}

string RegularExpressionOutput::type() {
	return "regular expression";
}
/**
 * Class Case Definitions
 * Case represents cases
 */
Case::Case() {
	reset();
}

void Case::reset() {
	input = "";
	output.clear();
	caseDescription = "";
	gradeReduction = std::numeric_limits<float>::min();
	failMessage = "";
	programToRun = "";
	programArgs = "";
	variation = "";
	expectedExitCode = std::numeric_limits<int>::min();
}

void Case::addInput(string s) {
	input += s;
}

string Case::getInput() {
	return input;
}

void Case::addOutput(string o) {
	output.push_back(o);
}

const vector< string > & Case::getOutput() {
	return output;
}

void Case::setFailMessage(const string &s) {
	failMessage = s;
}

string Case::getFailMessage() {
	return failMessage;
}
void Case::setCaseDescription(const string &s) {
	caseDescription = s;
}

string Case::getCaseDescription() {
	return caseDescription;
}
void Case::setGradeReduction(float g) {
	gradeReduction = g;
}

float Case::getGradeReduction() {
	return gradeReduction;
}

void Case::setExpectedExitCode(int e) {
	expectedExitCode = e;
}

int Case::getExpectedExitCode() {
	return expectedExitCode;
}
void Case::setProgramToRun(const string &s) {
	programToRun = s;
}

string Case::getProgramToRun() {
	return programToRun;
}

void Case::setProgramArgs(const string &s) {
	programArgs = s;
}

string Case::getProgramArgs() {
	return programArgs;
}

void Case::setVariation(const string &s) {
	variation = Tools::toLower(Tools::trim(s));
}

string Case::getVariation() {
	return variation;
}

/**
 * Class TestCase Definitions
 * TestCase represents cases of test
 */

void TestCase::cutOutputTooLarge(string &output) {
	if (output.size() > MAXOUTPUT) {
		outputTooLarge = true;
		output.erase(0, output.size() - MAXOUTPUT);
	}
}

void TestCase::readWrite(int fdread, int fdwrite) {
	const int MAX = 1024* 10 ;
	// Buffer size to read
	const int POLLREAD = POLLIN | POLLPRI;
	// Poll to read from program
	struct pollfd devices[2];
	devices[0].fd = fdread;
	devices[1].fd = fdwrite;
	char buf[MAX];
	devices[0].events = POLLREAD;
	devices[1].events = POLLOUT;
	int res = poll(devices, programInput.size()>0?2:1, 0);
	if (res == -1) // Error
		return;
	if (res == 0) // Nothing to do
		return;
	if (devices[0].revents & POLLREAD) { // Read program output
		int readed = read(fdread, buf, MAX);
		if (readed > 0) {
			sizeReaded += readed;
			if (programInput.size() > 1) {
				programOutputBefore += string(buf, readed);
				cutOutputTooLarge(programOutputBefore);
			} else {
				programOutputAfter += string(buf, readed);
				cutOutputTooLarge(programOutputAfter);
			}
		}
	}
	if (programInput.size() > 0 && devices[1].revents & POLLOUT) { // Write to program
		int written = write(fdwrite, programInput.c_str(), Tools::nextLine(
				programInput));
		if (written > 0) {
			programInput.erase(0, written);
		}
		if(programInput.size()==0){
			close(fdwrite);
		}
	}
}

void TestCase::addOutput(const string &o, const string &actualCaseDescription){
// actualCaseDescripction, used to get current test name for Output recognition
	if(ExactTextOutput::typeMatch(o))
		this->output.push_back(new ExactTextOutput(o));
	else if (RegularExpressionOutput::typeMatch(o))
		this->output.push_back(new RegularExpressionOutput(o, actualCaseDescription));
	else if(NumbersOutput::typeMatch(o))
		this->output.push_back(new NumbersOutput(o));
	else
		this->output.push_back(new TextOutput(o));
}

void TestCase::setEnvironment(const char **environment) {
	envv = environment;
}

void TestCase::setDefaultCommand() {
	command = "./vpl_test";
	argv = new const char*[2];
	argv[0] = command;
	argv[1] = NULL;
}

TestCase::TestCase(const TestCase &o) {
	id=o.id;
	correctOutput=o.correctOutput;
	correctExitCode = o.correctExitCode;
	outputTooLarge=o.outputTooLarge;
	programTimeout=o.programTimeout;
	executionError=o.executionError;
	strcpy(executionErrorReason,o.executionErrorReason);
	sizeReaded=o.sizeReaded;
	input=o.input;
	caseDescription=o.caseDescription;
	gradeReduction=o.gradeReduction;
	expectedExitCode = o.expectedExitCode;
	exitCode = o.exitCode;
	failMessage=o.failMessage;
	programToRun=o.programToRun;
	programArgs=o.programArgs;
	gradeReductionApplied=o.gradeReductionApplied;
	programOutputBefore=o.programOutputBefore;
	programOutputAfter=o.programOutputAfter;
	programInput=o.programInput;
	executionTimeNs=o.executionTimeNs;
	for(size_t i = 0; i < o.output.size(); i++){
		output.push_back(o.output[i]->clone());
	}
	setDefaultCommand();
}

TestCase& TestCase::operator=(const TestCase &o) {
	id=o.id;
	correctOutput=o.correctOutput;
	correctExitCode = o.correctExitCode;
	outputTooLarge=o.outputTooLarge;
	programTimeout=o.programTimeout;
	executionError=o.executionError;
	strcpy(executionErrorReason,o.executionErrorReason);
	sizeReaded=o.sizeReaded;
	input=o.input;
	caseDescription=o.caseDescription;
	gradeReduction=o.gradeReduction;
	failMessage=o.failMessage;
	programToRun=o.programToRun;
	programArgs=o.programArgs;
	expectedExitCode = o.expectedExitCode;
	exitCode = o.exitCode;
	gradeReductionApplied=o.gradeReductionApplied;
	programOutputBefore=o.programOutputBefore;
	programOutputAfter=o.programOutputAfter;
	programInput=o.programInput;
	executionTimeNs = o.executionTimeNs;
	for(size_t i=0; i<output.size(); i++)
		delete output[i];
	output.clear();
	for(size_t i=0; i<o.output.size(); i++){
		output.push_back(o.output[i]->clone());
	}
	return *this;
}

TestCase::~TestCase() {
	for(size_t i = 0; i < output.size(); i++)
		delete output[i];
}

TestCase::TestCase(int id, const string &input, const vector<string> &output,
		const string &caseDescription, const float gradeReduction,
		string failMessage, string programToRun, string programArgs, int expectedExitCode) {
	this->id = id;
	this->input = input;
	for(size_t i = 0; i < output.size(); i++){
		addOutput(output[i], caseDescription);
	}
	this->caseDescription = caseDescription;
	this->gradeReduction = gradeReduction;
	this->expectedExitCode = expectedExitCode;
	this->programToRun = programToRun;
	this->programArgs = programArgs;
	this->failMessage = failMessage;
	exitCode = std::numeric_limits<int>::min();
	outputTooLarge = false;
	programTimeout = false;
	executionError = false;
	correctOutput = false;
	correctExitCode = false;
	sizeReaded = 0;
	gradeReductionApplied =0;
	strcpy(executionErrorReason, "");
	executionTimeNs = 0;
	setDefaultCommand();
}

bool TestCase::isCorrectResult() {
	bool correct = correctOutput &&
			      ! programTimeout &&
				  ! outputTooLarge &&
				  ! executionError;
	return correct || (isExitCodeTested() && correctExitCode);
}

bool TestCase::isExitCodeTested() {
	return expectedExitCode != std::numeric_limits<int>::min();
}

float TestCase::getGradeReduction() {
	return gradeReduction;
}

void TestCase::setGradeReductionApplied(float r) {
	gradeReductionApplied=r;
}

float TestCase::getGradeReductionApplied() {
	return gradeReductionApplied;
}

string TestCase::getCaseDescription(){
	return caseDescription;
}

string TestCase::getCommentTitle(bool withGradeReduction=false) {
	char buf[100];
	string ret;
	sprintf(buf, "Test %d", id);
	ret = buf;
	if (caseDescription.size() > 0) {
		ret += ": " + caseDescription;
	}
	if(withGradeReduction && getGradeReductionApplied()>0){
		sprintf(buf," (%.3f)", -getGradeReductionApplied());
		ret += buf;
	}
	ret += '\n';
	return ret;
}

// string TestCase::getComment() {
// 	if (isCorrectResult()) {
// 		return "";
// 	}
// 	char buf[100];
// 	string ret;
// 	if(output.size()==0){
// 		ret += "Configuration error in the test case: the output is not defined";
// 	}
// 	if (programTimeout) {
// 		ret += "Program timeout\n";
// 	}
// 	if (outputTooLarge) {
// 		sprintf(buf, "Program output too large (%dKb)\n", sizeReaded / 1024);
// 		ret += buf;
// 	}
// 	if (executionError) {
// 		ret += executionErrorReason + string("\n");
// 	}
// 	if (isExitCodeTested() && ! correctExitCode) {
// 		char buf[250];
// 		sprintf(buf, "Incorrect exit code. Expected %d, found %d\n", expectedExitCode, exitCode);
// 		ret += buf;
// 	}
// 	if (! correctOutput) {
// 		if (failMessage.size()) {
// 			ret += failMessage + "\n";
// 		} else { 
// 			//****** Commented to hide test cases from the users in vpl start *********
// 			// ret += "Incorrect program output\n";
// 			// ret += " --- Input ---\n";
// 			// ret += Tools::caseFormat(input);
// 			// ret += "\n --- Program output ---\n";
// 			// ret += Tools::caseFormat(programOutputBefore + programOutputAfter);
// 			if(output.size()>0){
// 				// ret += "\n --- Expected output ("+output[0]->type()+")---\n";
// 				// ret += Tools::caseFormat(output[0]->studentOutputExpected());
// 				//****** Commented to hide test cases from the users in vpl end *********
// 			}
// 		}
// 	}
// 	return ret;
// }

string TestCase::getComment() {
	if(output.size()==0){
		return "Configuration error in the test case: the output is not defined";
	}
	if (correctOutput && !(programTimeout || outputTooLarge
			|| executionError)) {
		return programOutputAfter;
	}
	char buf[100];
	string ret;
	if (programTimeout) {
		ret += "Time Limit Exceeded\n";
	}
	if (outputTooLarge) {
		sprintf(buf, "Program output too large (%dKb)\n", sizeReaded / 1024);
		ret += buf;
	}
	if (executionError) {
		ret += executionErrorReason + string("\n");
	}
	if (!correctOutput) {
		//ret += "Incorrect program result\n";
		ret += " --- Input --- \n";
		ret += Tools::caseFormat(input);
		ret += " --- Program output ---\n";
		ret += Tools::caseFormat(programOutputAfter.substr(programOutputAfter.find("$") + 1));
		if(output.size()>0){
			ret += "--- Expected output ---\n";
			ret += Tools::caseFormat(output[0]->studentOutputExpected());
		}
		
		return ret+"`"+programOutputAfter;
	}
	return ret;
}

void TestCase::splitArgs(string programArgs) {
	int l = programArgs.size();
	int nargs = 1;
	char *buf = new char[programArgs.size() + 1];
	strcpy(buf, programArgs.c_str());
	argv = (const char **) new char*[programArgs.size() + 1];
	argv[0] = command;
	bool inArg = false;
	char separator = ' ';
	for(int i=0; i < l; i++) { // TODO improve
		if ( ! inArg ) {
			if ( buf[i] == ' ' ) {
				buf[i] = '\0';
				continue;
			} else if ( buf[i] == '\'' ) {
				argv[nargs++] = buf + i + 1;
				separator = '\'';
			} else if ( buf[i] == '"' ) {
				argv[nargs++] = buf + i + 1;
				separator = '"';
			} else if ( buf[i] != '\0') {
				argv[nargs++] = buf + i;
				separator = ' ';
			}
			inArg = true;
		} else {
			if ( buf[i] == separator  ) {
				buf[i] = '\0';
				separator = ' ';
				inArg = false;
			}
		}
	}
	argv[nargs] = NULL;
}

void TestCase::runTest(time_t timeout) {// Timeout in seconds
	time_t start = time(NULL);
	struct timeval hrstart;
	struct timeval hrend;
	gettimeofday(&hrstart, NULL);
	executionTimeNs = 0;
	int pp1[2]; // Send data
	int pp2[2]; // Receive data
	if (pipe(pp1) == -1 || pipe(pp2) == -1) {
		executionError = true;
		sprintf(executionErrorReason, "Internal error: pipe error (%s)",
				strerror(errno));
		return;
	}
	if ( programToRun > "" && programToRun.size() < 512) {
		command = programToRun.c_str();
	}
	if ( ! Tools::existFile(command) ){
		executionError = true;
		sprintf(executionErrorReason, "Execution file not found '%s'", command);
		return;
	}
	pid_t pid;
	if ( programArgs.size() > 0) {
		splitArgs(programArgs);
	}
	if ((pid = fork()) == 0) {
		// Execute
		close(pp1[1]);
		dup2(pp1[0], STDIN_FILENO);
		close(pp2[0]);
		dup2(pp2[1], STDOUT_FILENO);
		dup2(STDOUT_FILENO, STDERR_FILENO);
		setpgrp();
		execve(command, (char * const *) argv, (char * const *) envv);
		perror("Internal error, execve fails");
		abort(); //end of child
	}
	if (pid == -1) {
		executionError = true;
		sprintf(executionErrorReason, "Internal error: fork error (%s)",
				strerror(errno));
		return;
	}
	close(pp1[0]);
	close(pp2[1]);
	int fdwrite = pp1[1];
	int fdread = pp2[0];
	Tools::fdblock(fdwrite, false);
	Tools::fdblock(fdread, false);
	programInput = input;
	if(programInput.size()==0){ // No input
		close(fdwrite);
	}
	programOutputBefore = "";
	programOutputAfter = "";
	pid_t pidr;
	int status;
	exitCode = std::numeric_limits<int>::min();
	while ((pidr = waitpid(pid, &status, WNOHANG | WUNTRACED)) == 0) {
		readWrite(fdread, fdwrite);
		usleep(5000);
		// TERMSIG or timeout or program output too large?
		if (Stop::isTERMRequested() || (time(NULL) - start) >= timeout
				|| outputTooLarge) {
			if ((time(NULL) - start) >= timeout) {
				programTimeout = true;
			}
			kill(pid, SIGTERM); // Send SIGTERM normal termination
			int otherstatus;
			usleep(5000);
			if (waitpid(pid, &otherstatus, WNOHANG | WUNTRACED) == pid) {
				break;
			}
			if (kill(pid, SIGQUIT) == 0) { // Kill
				break;
			}
		}
	}
	if (pidr == pid) {
		if (WIFSIGNALED(status)) {
			int signal = WTERMSIG(status);
			executionError = true;
			sprintf(executionErrorReason,
					"Program terminated due to \"%s\" (%d)\n", strsignal(
							signal), signal);
		}
		if (WIFEXITED(status)) {
			exitCode = WEXITSTATUS(status);
		} else {
			executionError = true;
			strcpy(executionErrorReason,
					"Program terminated but unknown reason.");
		}
	} else if (pidr != 0) {
		executionError = true;
		strcpy(executionErrorReason, "waitpid error");
	}
	readWrite(fdread, fdwrite);
	correctExitCode = isExitCodeTested() && expectedExitCode == exitCode;
	correctOutput = match(programOutputAfter)
		     || match(programOutputBefore + programOutputAfter);
	gettimeofday(&hrend, NULL);
	long long seconds = (long long)(hrend.tv_sec - hrstart.tv_sec);
	long long useconds = (long long)(hrend.tv_usec - hrstart.tv_usec);
	if (useconds < 0) {
		seconds--;
		useconds += 1000000LL;
	}
	executionTimeNs = seconds * 1000000000LL + useconds * 1000LL;
	if (executionTimeNs < 0) {
		executionTimeNs = 0;
	}
}

bool TestCase::match(string data) {
	for (size_t i = 0; i < output.size(); i++)
		if (output[i]->match(data))
			return true;
	return false;
}

long long TestCase::getExecutionTimeNs() const {
	return executionTimeNs;
}

/**
 * Class Evaluation Definitions
 */

Evaluation::Evaluation() {
	grade = 0;
	ncomments = 0;
	nerrors = 0;
	nruns = 0;
	noGrade = true;
	tleflag=0;
	ttime=0;
}

Evaluation* Evaluation::getSinglenton() {
	if (singlenton == NULL) {
		singlenton = new Evaluation();
	}
	return singlenton; // Fixes by Jan Derriks
}

void Evaluation::deleteSinglenton(){
	if (singlenton != NULL) {
		delete singlenton;
		singlenton = NULL;
	}
}

// void Evaluation::addTestCase(Case &caso) {
// 	if ( caso.getVariation().size() && caso.getVariation() != variation ) {
// 		return;
// 	}
// 	testCases.push_back(TestCase(testCases.size() + 1, caso.getInput(), caso.getOutput(),
// 			caso.getCaseDescription(), caso.getGradeReduction(), caso.getFailMessage(),
// 			caso.getProgramToRun(), caso.getProgramArgs(), caso.getExpectedExitCode() ));
// }
void Evaluation::addTestCase(const string &input,
                             const vector<string> &output,
                             const string &caseDescription,
                             float gradeReduction)
{
    testCases.push_back(
        TestCase(
            testCases.size() + 1,
            input,
            output,
            caseDescription,
            gradeReduction,
            "", // failMessage
            "", // programToRun
            "", // programArgs
            std::numeric_limits<int>::min() // expectedExitCode
        )
    );
}

void Evaluation::removeLastNL(string &s) {
	if (s.size() > 0 && s[s.size() - 1] == '\n') {
		s.resize(s.size() - 1);
	}
}

bool Evaluation::cutToEndTag(string &value, const string &endTag) {
	size_t pos;
	if (endTag.size() && (pos = value.find(endTag)) != string::npos) {
		value.resize(pos);
		return true;
	}
	return false;
}

void Evaluation::loadTestCases(string fname) {
	if(!Tools::existFile(fname)) return;
	const char *CASE_TAG = "case=";
	const char *INPUT_TAG = "input=";
	const char *INPUT_END_TAG = "inputend=";
	const char *OUTPUT_TAG = "output=";
	const char *OUTPUT_END_TAG = "outputend=";
	const char *GRADEREDUCTION_TAG = "gradereduction=";
	const char *FAILMESSAGE_TAG = "failmessage=";
	const char *PROGRAMTORUN_TAG = "programtorun=";
	const char *PROGRAMARGS_TAG = "programarguments=";
	const char *EXPECTEDEXITCODE_TAG = "expectedexitcode=";
	const char *VARIATION_TAG = "variation=";
	enum {
		regular, ininput, inoutput
	} state;
	bool inCase = false;
	vector<string> lines = Tools::splitLines(Tools::readFile(fname));
    remove(fname.c_str());
	string inputEnd = "";
	string outputEnd = "";
	Case caso;
	string output = "";
	string tag, value;
	/* must be changed from String
	 * to pair type (regexp o no) and string. */
	state = regular;
	int nlines = lines.size();
	for (int i = 0; i < nlines; i++) {
		string &line = lines[i];
		Tools::parseLine(line, tag, value);
		if (state == ininput) {
			if (inputEnd.size()) { // Check for end of input.
				size_t pos = line.find(inputEnd);
				if (pos == string::npos) {
					caso.addInput(line + "\n");
				} else {
					cutToEndTag(line, inputEnd);
					caso.addInput(line);
					state = regular;
					continue; // Next line.
				}
			} else if (tag.size() && (tag == OUTPUT_TAG || tag
					== GRADEREDUCTION_TAG || tag == CASE_TAG)) {// New valid tag.
				state = regular;
				// Go on to process the current tag.
			} else {
				caso.addInput(line + "\n");
				continue; // Next line.
			}
		} else if (state == inoutput) {
			if (outputEnd.size()) { // Check for end of output.
				size_t pos = line.find(outputEnd);
				if (pos == string::npos) {
					output += line + "\n";
				} else {
					cutToEndTag(line, outputEnd);
					output += line;
					caso.addOutput(output);
					output = "";
					state = regular;
					continue; // Next line.
				}
			} else if (tag.size() && (tag == INPUT_TAG || tag == OUTPUT_TAG
					|| tag == GRADEREDUCTION_TAG || tag == CASE_TAG)) {// New valid tag.
				removeLastNL(output);
				caso.addOutput(output);
				output = "";
				state = regular;
			} else {
				output += line + "\n";
				continue; // Next line.
			}
		}
		if (state == regular && tag.size()) {
			if (tag == INPUT_TAG) {
				inCase = true;
				if (cutToEndTag(value, inputEnd)) {
					caso.addInput(value);
				} else {
					state = ininput;
					caso.addInput(value + '\n');
				}
			} else if (tag == OUTPUT_TAG) {
				inCase = true;
				if (cutToEndTag(value, outputEnd))
					caso.addOutput(value);
				else {
					state = inoutput;
					output = value + '\n';
				}
			} else if (tag == GRADEREDUCTION_TAG) {
				inCase = true;
				value = Tools::trim(value);
				// A percent value?
				if( value.size() > 1 && value[ value.size() - 1 ] == '%' ){
					float percent = atof(value.c_str());
					caso.setGradeReduction((grademax-grademin)*percent/100);
				}else{
					caso.setGradeReduction( atof(value.c_str()) );
				}
			} else if (tag == EXPECTEDEXITCODE_TAG) {
				caso.setExpectedExitCode( atoi(value.c_str()) );
			} else if (tag == PROGRAMTORUN_TAG) {
				caso.setProgramToRun(Tools::trim(value));
			} else if (tag == PROGRAMARGS_TAG) {
				caso.setProgramArgs(Tools::trim(value));
			} else if (tag == FAILMESSAGE_TAG) {
				caso.setFailMessage(Tools::trim(value));
			} else if (tag == VARIATION_TAG) {
				caso.setVariation(value);
			} else if (tag == INPUT_END_TAG) {
				inputEnd = Tools::trim(value);
			} else if (tag == OUTPUT_END_TAG) {
				outputEnd = Tools::trim(value);
			} else if (tag == CASE_TAG) {
				if (inCase) {
					addTestCase(
            caso.getInput(),
            caso.getOutput(),
            caso.getCaseDescription(),
            caso.getGradeReduction()
        );
					  
					caso.reset();
				}
				inCase = true;
				caso.setCaseDescription( Tools::trim(value) );
			} else {
				if ( line.size() > 0 ) {
					char buf[250];
					sprintf(buf,"Syntax error: unexpected line %d", i+1);
					addFatalError(buf);
				}
			}
		}
	}
	// TODO review
	if (state == inoutput) {
		removeLastNL(output);
		caso.addOutput(output);
	}
	if (inCase) { // Last case => save current.
		addTestCase(
    caso.getInput(),
    caso.getOutput(),
    caso.getCaseDescription(),
    caso.getGradeReduction()
);
	}
}

bool Evaluation::loadParams() {

    std::cerr << "DEBUG: Loading VPL parameters..." << std::endl;

    grademin = Tools::getenv("VPL_GRADEMIN", 0.0);
    std::cerr << "DEBUG: VPL_GRADEMIN = " << grademin << std::endl;

    grademax = Tools::getenv("VPL_GRADEMAX", 10);
    std::cerr << "DEBUG: VPL_GRADEMAX = " << grademax << std::endl;

    maxtime = (int) Tools::getenv("VPL_MAXTIME", 20);
    std::cerr << "DEBUG: VPL_MAXTIME = " << maxtime << std::endl;

    variation = Tools::toLower(
                    Tools::trim(
                        Tools::getenv("VPL_VARIATION","")
                    )
                );
    std::cerr << "DEBUG: VPL_VARIATION = " << variation << std::endl;

     // Read TLE in milliseconds from Moodle
double rawTle = Tools::getenv("VPL_TLELIMIT", 0.0);
tletime = (long long)rawTle;

std::cerr << "DEBUG: VPL_TLELIMIT (raw) = " << rawTle << std::endl;
std::cerr << "DEBUG: TLE Limit Used (ns) = " << tletime << std::endl;


    totalExecutionTimeNs = 0;
    tleFlag = false;

    noGrade = grademin >= grademax;
    std::cerr << "DEBUG: noGrade flag = " << noGrade << std::endl;

    std::cerr << "DEBUG: loadParams() finished." << std::endl;

    return true;
}

void Evaluation::addFatalError(const char *m) {
	float reduction = grademax - grademin;
	if (ncomments >= MAXCOMMENTS)
		ncomments = MAXCOMMENTS - 1;

	snprintf(titles[ncomments], MAXCOMMENTSTITLELENGTH, "%s", m);
	snprintf(titlesGR[ncomments], MAXCOMMENTSTITLELENGTH, "%s (%.2f)", m, reduction);
	strcpy(comments[ncomments], "");
	ncomments ++;
	grade = grademin;
}



void Evaluation::runTests() {

	printf("\n===== DEBUG: ENTER runTests() =====\n");

	if (testCases.size() == 0) {
		printf("DEBUG: No test cases found.\n");
		return;
	}

	if (maxtime < 0) {
		printf("DEBUG: Global timeout before starting tests.\n");
		addFatalError("Global timeout");
		return;
	}

	nerrors = 0;
	nruns = 0;
	grade = grademax;

	printf("DEBUG: grademax = %.2f\n", grademax);
	printf("DEBUG: grademin = %.2f\n", grademin);
	printf("DEBUG: Total testCases = %lu\n", (unsigned long)testCases.size());
	printf("DEBUG: maxtime = %d\n", maxtime);
	printf("DEBUG: tletime = %d\n", tletime);

	float defaultGradeReduction = (grademax - grademin) / testCases.size();
	int timeout = maxtime / testCases.size();

	printf("DEBUG: defaultGradeReduction = %.2f\n", defaultGradeReduction);
	printf("DEBUG: timeout per test = %d\n", timeout);

	for (int i = 0; i < testCases.size(); i++) {

		printf("\n----- DEBUG: START TEST %d -----\n", i+1);

		printf("Testing %d/%lu : %s\n",
		       i+1,
		       (unsigned long)testCases.size(),
		       testCases[i].getCaseDescription().c_str());

		printf("DEBUG: Grade before test = %.2f\n", grade);
		printf("DEBUG: Global elapsed time = %d\n", Timer::elapsedTime());

		if (timeout <= 1 || Timer::elapsedTime() >= maxtime) {
			printf("DEBUG: Global timeout triggered inside loop.\n");
			grade = grademin;
			addFatalError("Global timeout");
			return;
		}

		if (maxtime - Timer::elapsedTime() < timeout) {
			timeout = maxtime - Timer::elapsedTime();
			printf("DEBUG: Adjusted timeout = %d\n", timeout);
		}

		testCases[i].runTest(timeout);
		nruns++;

		printf("DEBUG: Test executed.\n");
		printf("DEBUG: isCorrectResult = %d\n",
		       testCases[i].isCorrectResult());

		if (!testCases[i].isCorrectResult()) {

			printf("DEBUG: Wrong Answer detected.\n");

			if (Stop::isTERMRequested())
				break;

			float gr = testCases[i].getGradeReduction();
			printf("DEBUG: Custom grade reduction = %.5f\n", gr);

			if (gr == std::numeric_limits<float>::min()) {
				printf("DEBUG: Using default grade reduction.\n");
				testCases[i].setGradeReductionApplied(defaultGradeReduction);
			}
			else {
				testCases[i].setGradeReductionApplied(gr);
			}

			printf("DEBUG: Reduction applied = %.2f\n",
			       testCases[i].getGradeReductionApplied());

			grade -= testCases[i].getGradeReductionApplied();

			if(grade<grademin)
				grade=grademin;

			printf("DEBUG: Grade after WA reduction = %.2f\n", grade);

			nerrors++;

			if(ncomments<MAXCOMMENTS){
				printf("DEBUG: Storing comment for WA.\n");

				strncpy(titles[ncomments],
					testCases[i].getCommentTitle().c_str(),
					MAXCOMMENTSTITLELENGTH);

				strncpy(titlesGR[ncomments],
					testCases[i].getCommentTitle(true).c_str(),
					MAXCOMMENTSTITLELENGTH);

				strncpy(comments[ncomments],
					testCases[i].getComment().c_str(),
					MAXCOMMENTSLENGTH);

				ncomments++;
			}
		}

	/* TLE Grade Reduction Logic Start */

		std::string commentStr = testCases[i].getComment();
		printf("DEBUG: Raw comment string = %s\n", commentStr.c_str());

		char *etim;
		int tstim = 0;

		etim = strrchr(const_cast<char*>(commentStr.c_str()), '$');

		if(etim){

			printf("DEBUG: '$' found in comment.\n");

			etim++;
			tstim=atoi(etim);

			printf("DEBUG: Extracted execution time (ns) = %d\n", tstim);

			ttime+= tstim;

			if(tstim>1000000000)
				printf("Time Taken = %.3f Seconds\n",tstim/1000000000.0);
			else if(tstim>1000000)
				printf("Time Taken = %.3f Milli Sec\n",tstim/1000000.0);
			else if(tstim>1000)
				printf("Time Taken = %.3f Micro Sec\n",tstim/1000.0);
			else
				printf("Time Taken = %d Nano Sec\n",tstim );

			if(tletime>0){

				printf("DEBUG: tletime = %d\n", tletime);
				printf("DEBUG: isCorrectResult = %d\n",
				       testCases[i].isCorrectResult());
				printf("DEBUG: Grade before TLE check = %.2f\n", grade);

				if(tstim>tletime && testCases[i].isCorrectResult()){

					printf("DEBUG: TLE CONDITION TRUE.\n");

					testCases[i].setGradeReductionApplied(defaultGradeReduction);
					grade -= testCases[i].getGradeReductionApplied();

					printf("DEBUG: TLE reduction applied = %.2f\n",
					       defaultGradeReduction);
					printf("DEBUG: Grade after TLE reduction = %.2f\n",
					       grade);

					strncpy(titles[ncomments],
						testCases[i].getCommentTitle().c_str(),
						MAXCOMMENTSTITLELENGTH);

					strncpy(titlesGR[ncomments],
						testCases[i].getCommentTitle(true).c_str(),
						MAXCOMMENTSTITLELENGTH);

					strncpy(comments[ncomments],
						testCases[i].getComment().c_str(),
						MAXCOMMENTSLENGTH);

					ncomments++;

					printf("Time Limit Exceeded\n");
				}
				else{
					printf("DEBUG: Within time limit.\n");
				}

				printf("DEBUG: Grade after TLE check = %.2f\n", grade);
			}
		}
		else{
			printf("DEBUG: No '$' time marker found in comment.\n");
		}

		printf("----- DEBUG: END TEST %d | Current Grade = %.2f -----\n",
		       i+1, grade);
	}

	printf("\n===== DEBUG: EXIT runTests() =====\n");
	printf("DEBUG: Total Runs = %d\n", nruns);
	printf("DEBUG: Total Errors = %d\n", nerrors);
	printf("DEBUG: Final Grade = %.2f\n", grade);
}


void Evaluation::outputEvaluation() {
	const char* stest[]={" test","tests"};
	if (testCases.size() > 0) {
		if (ncomments > 1) {
		/*	printf("\n<|--\n");
			printf("-Failed tests\n");
			for (int i = 0; i < ncomments; i++) {
				printf("%s", titles[i]);
			}
			printf("--|>\n");*/
		}
		if (ncomments > 0) {
			printf("\n<|--\n");
							
							
			for (int i = 0; i < ncomments; i++) {
				printf("%s", titlesGR[i]);
				char *et,*temp,*foundtext;int tst=0,len=0;
						
						temp=comments[i];
						if(strrchr(temp, '`')!=NULL)
						 len=(strrchr(temp, '`')-temp)+1;
						
    
    // into buffer instead of printing on stdout


				if(tletime){
						et=comments[i];
 						if(len>0){
							char buffer[len];		
							strcpy(buffer, temp);
							buffer[len-1]='\0';
							strcpy(foundtext,buffer);
						 }
						et = strrchr(et, '$'); 
						
					if(et){
						et++;
						tst=atoi(et);
					}
				}
				if(tst>tletime){
					tleflag=1;
					printf("Time Limit Exceeded (%d nano sec)\n\n",tletime); // Hide Program output for failed testcase
					if(len>0)
					printf("%s",foundtext);
				}
				else{
					if(showcom==0)
					printf("Test Case Failed\n\n");
					else if(showcom==1 && len>0){
					printf("\nIncorrect Output\n");
					printf("%s",foundtext);
					}
				}
			}
			printf("--|>\n\n");
		}
		if (nruns > 0) {
			int passed=nruns-nerrors;
			struct timeval tp;
			gettimeofday(&tp, NULL);
			long int end = tp.tv_sec * 1000 + tp.tv_usec / 1000;
			printf("\n<|--");
			
			printf("-----Summary of tests------|>\n");

			printf(">+------------------------------+\n");
			printf(">| %2d %s run/%2d %s passed |\n",
					nruns, nruns==1?stest[0]:stest[1],
					passed, passed==1?stest[0]:stest[1]); //Taken from Dominique Thiebaut
			printf(">+------------------------------+\n");
			string result="";
			
			

		}

        /* Ram Mohan Start Test Case Time Summery Start
		char *etime;int tstime=0;
			for (int i = 0; i < testCases.size(); i++) {
				etime=comments[i];
				etime = strrchr(etime, '$'); 
				if(etime){
				etime++;
				tstime=atoi(etime);
				ttime+= tstime;
					if(tstime>1000000)
						printf("Case %d Time Taken = %.3f Milli Sec\n",i+1,tstime/1000000.0);
					else if(tstime>1000)
						printf("Case %d Time Taken = %.3f Micro Sec\n",i+1,tstime/1000.0);
					else
						printf("Case %d Time Taken = %d Nano Sec\n",i+1,tstime );
				
			  }
			}	
			// Ram Mohan Start Test Case Time Summery  End
			*/

		if(!noGrade){
			char buf[100];
			sprintf(buf, "%5.2f", grade);
			int len = strlen(buf);
			if (len > 3 && strcmp(buf + (len - 3), ".00") == 0)
				buf[len - 3] = 0;
			printf("\nGrade :=>>%s", buf); // added evaluation time text to get evaluation time
			printf("\nExecution Time :=>>%d$%d", ttime,tleflag); // added evaluation time text to get evaluation time

		}
	} else {
		printf("<|--\n");
		printf("-No test case found\n");
		printf("--|>\n");
	}
	fflush(stdout);
}

void nullSignalCatcher(int n) {
	//printf("Signal %d\n",n);
}

void signalCatcher(int n) {
	//printf("Signal %d\n",n);
	if (Stop::isTERMRequested()) {
		Evaluation* obj = Evaluation::getSinglenton();
		obj->outputEvaluation();
		abort();
	}
	Evaluation *obj = Evaluation::getSinglenton();
	if (n == SIGTERM) {
		obj->addFatalError("Global test timeout (TERM signal received)");
	} else {
		obj->addFatalError("Internal test error");
		obj->outputEvaluation();
		Stop::setTERMRequested();
		abort();
	}
	alarm(1);
}

void setSignalsCatcher() {
	// Removes as many signal controllers as possible
	for(int i=0;i<31; i++)
		signal(i, nullSignalCatcher);
	signal(SIGINT, signalCatcher);
	signal(SIGQUIT, signalCatcher);
	signal(SIGILL, signalCatcher);
	signal(SIGTRAP, signalCatcher);
	signal(SIGFPE, signalCatcher);
	signal(SIGSEGV, signalCatcher);
	signal(SIGALRM, signalCatcher);
	signal(SIGTERM, signalCatcher);
}

int main(int argc, char *argv[], const char **env) {
	Timer::start();
	TestCase::setEnvironment(env);
	setSignalsCatcher();
	Evaluation* obj = Evaluation::getSinglenton();
	obj->loadParams();
	obj->loadTestCases("evaluate.cases");
	obj->runTests();
	obj->outputEvaluation();
	return EXIT_SUCCESS;
}